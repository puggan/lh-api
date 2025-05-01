<?php

namespace App\Http\Controllers;

use App\Models\Bookings;
use App\Models\Periods;
use App\Models\Places;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CalenderController
{
    public static function calender(): JsonResponse
    {
        $events = [];
        $query = Periods::query();
        $query->with(["place", "bookings"]);
        /** @var Periods $period */
        foreach ($query->get() as $period) {
            if ($period->bookings->isEmpty()) {
                if ($period->start_date <= date('Y-m-d')) {
                    $status = "Passed";
                } else {
                    $status = "Available";
                }
            }elseif ($period->bookings->where('status', '=', 'booked')->isEmpty()) {
                $status = "Pending";
            }else {
                $status = "Booked";
            }
            $events[] = [
                "id" => $period->id,
                "places_id" => $period->places_id,
                "place" => $period->place->name,
                "start_date" => $period->start_date,
                "end_date" => $period->end_date,
                "status" => $status,
            ];
        }
        return new JsonResponse($events);
    }

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
