<?php

namespace App\Services;

use App\Models\Bookings;

/**
 * Messages to the booker and to the admins (info@hgf-tuve.se).
 *
 * Not implemented yet: how messages are sent (mail(), Laravel Mail, SMS, ...) is still to be decided.
 */
class Communication
{
    /** Informs the booker of $booking, e.g. that the booking was declined. */
    public static function informUser(Bookings $booking, string $subject, string $message): void
    {
        // todo: send to the booker ($booking->email / $booking->phone)
    }

    /** Informs the admins, e.g. that a new booking request has arrived. */
    public static function informAdmin(string $subject, string $message, ?Bookings $booking = null): void
    {
        // todo: send to the admins
    }
}
