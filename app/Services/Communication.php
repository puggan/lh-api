<?php

namespace App\Services;

use App\Models\Bookings;

/**
 * Messages to the booker and to the admins.
 *
 * Not implemented yet: messages will be sent through an API that is still to be chosen
 * (not PHP's mail()). Until then nothing is sent. While running tests the messages are
 * collected in self::$sent, so tests can check what would have been sent.
 */
class Communication
{
    public const ADMIN_EMAIL = 'info@hgf-tuve.se';

    /** @var list<array{to: string, subject: string, message: string, replyTo: string}> messages "sent" during tests */
    public static array $sent = [];

    /** Informs the booker of $booking, e.g. that the booking was declined. */
    public static function informUser(Bookings $booking, string $subject, string $message): void
    {
        self::send($booking->email, $subject, $message, self::ADMIN_EMAIL);
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

        // todo: send through the communication API
    }
}
