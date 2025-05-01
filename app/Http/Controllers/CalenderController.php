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

}
