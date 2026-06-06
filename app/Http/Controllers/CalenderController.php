<?php

namespace App\Http\Controllers;

use App\Models\Periods;
use App\Models\WpUser;
use Illuminate\Http\JsonResponse;

class CalenderController
{
    public static function calender(): JsonResponse
    {
        $user = WpUser::loggedIn();
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
            } elseif ($period->bookings->where('status', '=', 'booked')->isEmpty()) {
                $status = "Pending";
            } else {
                $status = "Booked";
            }
            $events[] = [
                "id" => $period->id,
                "places_id" => $period->places_id,
                "place" => $period->place->name,
                "start_date" => $period->start_date,
                "end_date" => $period->end_date,
                "status" => $status,
                "bookings" => $user ? $period->bookings : null
            ];
        }
        return new JsonResponse($events);
    }

}
