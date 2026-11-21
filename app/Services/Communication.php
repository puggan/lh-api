<?php

namespace App\Services;

use App\Models\Bookings;

/**
 * Messages to the booker and to the admins.
 *
 * Sent with PHP's mail() (the server's sendmail), as the booking endpoint always has.
 * While running tests nothing is sent; the messages are collected in self::$sent instead.
 */
class Communication
{
    public const ADMIN_EMAIL = 'info@hgf-tuve.se';

    /** @var list<array{to: string, subject: string, message: string, replyTo: string}> messages "sent" during tests */
    public static array $sent = [];

    /** Informs the booker of $booking, e.g. that the booking was declined. */
    public static function informUser(Bookings $booking, string $subject, string $message): void
    {
        self::send($booking->email, $subject, $message, mb_encode_mimeheader('LH Höjden', 'UTF-8') . ' <' . self::ADMIN_EMAIL . '>');
    }

    /** Informs the admins, e.g. that a new booking request has arrived. Replies go to the booker. */
    public static function informAdmin(string $subject, string $message, ?Bookings $booking = null): void
    {
        self::send(self::ADMIN_EMAIL, $subject, $message, $booking?->email);
    }

    private static function send(string $to, string $subject, string $message, ?string $replyTo): void
    {
        if (app()->runningUnitTests()) {
            self::$sent[] = ['to' => $to, 'subject' => $subject, 'message' => $message, 'replyTo' => (string)$replyTo];
            return;
        }

        $headers = [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ];
        if ($replyTo) {
            $headers['Reply-To'] = $replyTo;
        }
        // A message that can't be sent must not break the booking, approval or decline that caused it:
        // the change is already saved. Laravel turns mail()'s warnings (e.g. no sendmail) into exceptions.
        try {
            $sent = mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $message, $headers);
            $error = $sent ? null : 'mail() returned false';
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }
        if ($error !== null) {
            // error_log(), not Laravel's log: it reaches the web server's error log even if storage/ isn't writable.
            error_log("Communication: could not send \"{$subject}\" to {$to}: {$error}");
        }
    }
}
