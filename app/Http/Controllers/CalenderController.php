<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireWpAdmin;
use App\Models\Periods;
use App\Models\WpUser;
use Illuminate\Http\JsonResponse;

class CalenderController
{
    public static function calender(): JsonResponse
    {
        // The bookings (personal data) only for admins.
        $isAdmin = (bool)WpUser::loggedIn()?->hasCapability(RequireWpAdmin::CAPABILITY);
        $events = [];
        $query = Periods::query();
        $query->with(["place", "bookings"]);
        /** @var Periods $period */
        foreach ($query->get() as $period) {
            // Rejected, unbooked and deleted bookings don't block the period.
            if ($period->bookings->contains('status', 'booked')) {
                $status = "Booked";
            } elseif ($period->start_date <= date('Y-m-d')) {
                $status = "Passed";
            } elseif ($period->bookings->contains('status', 'pending')) {
                $status = "Pending";
            } else {
                $status = "Available";
            }
            $events[] = [
                "id" => $period->id,
                "places_id" => $period->places_id,
                "place" => $period->place->name,
                "start_date" => $period->start_date,
                "end_date" => $period->end_date,
                "status" => $status,
                "bookings" => $isAdmin ? $period->bookings : null
            ];
        }
        return new JsonResponse($events);
    }

}
