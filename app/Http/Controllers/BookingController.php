<?php

namespace App\Http\Controllers;

use App\Models\Bookings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController
{
    public static function book(Request $request): JsonResponse
    {
        $booking = new Bookings();
        $booking->periods_id = $request->input('periods_id');
        $booking->name = $request->input('name');
        $booking->phone = $request->input('phone');
        $booking->email = $request->input('email');
        $booking->street = $request->input('street');
        $booking->apartment = $request->input('apartment');
        $booking->zipcode = $request->input('zipcode');
        $booking->city = $request->input('city');
        $booking->description = $request->input('purpose');
        $booking->terms_id = $request->input('terms_id');
        $booking->save();
        $booking->load('period');

        $bookingDataAsText = json_encode($booking, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_SLASHES);

        mail(
            'info@hgf-tuve.se',
            'bokningsförfrågan',
            <<<TEXT_BLOCK
                Det har kommit in en boknings förfrågan.
                Var vänlig svara inom kort från:

                https://hgf-tuve.se/bokningskalender-for-lokalen/request

                Bokningen: {$bookingDataAsText}
                TEXT_BLOCK,
            "Reply-To: {$booking->email}",
        );

        mail(
            $booking->email,
            'bokningsförfrågan mottagen',
            <<<TEXT_BLOCK
                Vi har tagit i mot din boknings förfrågan,
                och kommer svara inom kort.

                Bokningen: {$bookingDataAsText}
                TEXT_BLOCK,
            'Reply-To: "LH Höjden"<info@hgf-tuve.se>',
        );

        return new JsonResponse($booking);
    }
}
