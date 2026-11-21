<?php

namespace App\Services;

use App\Models\Bookings;
use App\Models\Places;
use Illuminate\Support\Facades\Http;

/**
 * Messages to the booker and to the admin, as SMS through 46elks (https://46elks.com/docs/send-sms).
 *
 * Configured with constants in the shared wp-config.php (see config/services.php):
 * ELKS_API_USERNAME and ELKS_API_PASSWORD (without them nothing is sent), optional ELKS_FROM
 * (sender name, 3-11 letters/digits, not starting with a digit) and ELKS_DRYRUN
 * (true: 46elks only validates, nothing is sent).
 *
 * A message that can't be sent never breaks the request that sent it; the failure goes to the error log.
 * While running tests the messages are also collected in self::$sent.
 */
class Communication
{
    public const API_URL = 'https://api.46elks.com/a1/sms';
    public const DEFAULT_FROM = 'LHHojden';

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
        $to = '+46' . $phone;
        if (app()->runningUnitTests()) {
            self::$sent[] = ['to' => $to, 'message' => $message];
        }

        $username = config('services.elks.username');
        $password = config('services.elks.password');
        if (!$username || !$password) {
            return;
        }

        $parameters = [
            'from' => config('services.elks.from') ?: self::DEFAULT_FROM,
            'to' => $to,
            'message' => $message,
        ];
        if (config('services.elks.dryrun')) {
            $parameters['dryrun'] = 'yes';
        }

        try {
            $response = Http::withBasicAuth($username, $password)->asForm()->timeout(10)->post(self::API_URL, $parameters);
            if (!$response->successful()) {
                $error = "HTTP {$response->status()}: " . mb_substr(trim($response->body()), 0, 200);
            } elseif ($response->json('status') === 'failed') {
                $error = 'status failed';
            } else {
                $error = null;
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }
        if ($error !== null) {
            // error_log(), not Laravel's log: it reaches the web server's error log even if storage/ isn't writable.
            error_log("Communication: SMS to {$to} failed: {$error}");
        }
    }
}
