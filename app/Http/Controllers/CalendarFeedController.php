<?php

namespace App\Http\Controllers;

use App\Models\Bookings;
use App\Models\Periods;
use App\Models\Places;
use App\Support\ICalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * iCalendar feeds to subscribe to from Google Calendar (Other calendars → From URL).
 *
 * One pair of feeds per place:
 * - <place>/public.ics: the days/periods that can be booked.
 * - <place>/admin/<token>.ics: the booked bookings, with the booker's details. Calendar apps fetch feeds
 *   without a login, so the URL holds a secret token, derived per place from LH_CALENDAR_TOKEN
 *   in the shared wp-config.php (one place's URL doesn't reveal another's).
 */
class CalendarFeedController
{
    public const BOOKING_PAGE_URL = 'https://hgf-tuve.se/lokalen/';

    public static function publicFeed(int $place): Response
    {
        $placeModel = Places::query()->find($place);
        if (!$placeModel) {
            return self::notFound();
        }
        $calendar = new ICalendar("{$placeModel->name} – lediga dagar");
        $periods = Periods::query()
            ->with(['place', 'bookings'])
            ->where('places_id', '=', $place)
            ->where('end_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->get();
        foreach ($periods as $period) {
            if ($period->status() !== 'Available') {
                continue;
            }
            $calendar->addAllDayEvent(
                "period-{$period->id}@hgf-tuve.se",
                $period->start_date,
                $period->end_date,
                "Ledig: {$period->place->name}",
                'Boka på ' . self::BOOKING_PAGE_URL,
            );
        }
        return self::response($calendar, 'public, max-age=300');
    }

    public static function adminFeed(int $place, string $token): Response
    {
        $expected = self::adminToken($place);
        $placeModel = Places::query()->find($place);
        if (!$expected || !$placeModel || !hash_equals($expected, $token)) {
            return self::notFound();
        }

        $calendar = new ICalendar("{$placeModel->name} – bokningar");
        $bookings = Bookings::query()
            ->with('period.place')
            ->where('status', '=', 'booked')
            ->whereHas('period', fn ($period) => $period->where('places_id', '=', $place))
            ->get();
        foreach ($bookings as $booking) {
            $period = $booking->period;
            $calendar->addAllDayEvent(
                "booking-{$booking->id}@hgf-tuve.se",
                $period->start_date,
                $period->end_date,
                "Bokad: {$booking->name}, lgh {$booking->apartment}",
                implode("\n", array_filter([
                    "Lokal: {$period->place->name}",
                    "Namn: {$booking->name}",
                    "Lägenhet: {$booking->apartment}",
                    "Telefon: 0{$booking->phone}",
                    "E-post: {$booking->email}",
                    "Syfte: {$booking->description}",
                    $booking->deposit ? "Deposition: {$booking->deposit} kr" : null,
                ])),
            );
        }
        return self::response($calendar, 'private, no-store');
    }

    /** Admin: the URLs to subscribe to, per place (the admin one only when a secret is configured). */
    public static function feeds(): JsonResponse
    {
        return new JsonResponse(Places::query()->orderBy('id')->get()->map(fn (Places $place) => [
            'places_id' => $place->id,
            'place' => $place->name,
            'public' => url("/api/calender/{$place->id}/public.ics"),
            'admin' => ($token = self::adminToken($place->id)) ? url("/api/calender/{$place->id}/admin/{$token}.ics") : null,
        ]));
    }

    /** The admin feed's token for a place: an HMAC of the place id with LH_CALENDAR_TOKEN. */
    public static function adminToken(int $place): ?string
    {
        $secret = config('services.calendar.token');
        return $secret ? substr(hash_hmac('sha256', "admin-calendar-place-{$place}", $secret), 0, 32) : null;
    }

    /** Plain text, not abort(404): Laravel's HTML error page needs a writable storage/ to compile. */
    private static function notFound(): Response
    {
        return new Response('Not found', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    private static function response(ICalendar $calendar, string $cacheControl): Response
    {
        return new Response($calendar->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Cache-Control' => $cacheControl,
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
