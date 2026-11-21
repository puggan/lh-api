<?php

namespace App\Services;

use App\Models\Bookings;
use App\Models\Places;
use Illuminate\Support\Facades\Http;

/**
 * Messages to the booker and to the admin, as SMS through SMSAPI (https://www.smsapi.com/docs/).
 *
 * Configured with constants in the shared wp-config.php (see config/services.php):
 * SMSAPI_TOKEN (without it nothing is sent), optional SMSAPI_FROM (a verified sender name)
 * and SMSAPI_TEST (true: SMSAPI only validates, nothing is sent or charged).
 * SMSAPI only accepts requests from whitelisted IPs (ns1).
 *
 * A message that can't be sent never breaks the request that sent it; the failure goes to the error log.
 * While running tests the messages are also collected in self::$sent.
 */
class Communication
{
    public const API_URL = 'https://api.smsapi.com/sms.do';

    /** @var list<array{to: string, message: string}> messages "sent" during tests */
    public static array $sent = [];

    /** Informs the booker of $booking, e.g. that the booking was declined. */
    public static function informUser(Bookings $booking, string $message): void
    {
        self::send($booking->phone, $message);
    }

    /** Informs the admin of $place (places.admin_phone), e.g. that a new booking request has arrived. */
    public static function informAdmin(Places $place, string $message): void
    {
        if (!$place->admin_phone) {
            error_log("Communication: place {$place->id} has no admin_phone, message not sent");
            return;
        }
        self::send($place->admin_phone, $message);
    }

    /** @param int $phone Swedish number as stored, without the leading 0 */
    private static function send(int $phone, string $message): void
    {
        $to = '46' . $phone;
        if (app()->runningUnitTests()) {
            self::$sent[] = ['to' => $to, 'message' => $message];
        }

        $token = config('services.smsapi.token');
        if (!$token) {
            return;
        }

        $parameters = ['to' => $to, 'message' => $message, 'format' => 'json'];
        if ($from = config('services.smsapi.from')) {
            $parameters['from'] = $from;
        }
        if (config('services.smsapi.test')) {
            $parameters['test'] = 1;
        }

        try {
            $response = Http::withToken($token)->asForm()->timeout(10)->post(self::API_URL, $parameters);
            // Errors come as {"error": 98, "message": "..."} with HTTP 200 (seen on ns1), so check the body, not only the status.
            $error = $response->json('error') !== null
                ? "error {$response->json('error')}: {$response->json('message')}"
                : ($response->successful() ? null : "HTTP {$response->status()}");
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }
        if ($error !== null) {
            // error_log(), not Laravel's log: it reaches the web server's error log even if storage/ isn't writable.
            error_log("Communication: SMS to {$to} failed: {$error}");
        }
    }
}
