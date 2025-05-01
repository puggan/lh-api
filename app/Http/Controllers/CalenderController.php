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
        return new JsonResponse($booking);
    }
}
