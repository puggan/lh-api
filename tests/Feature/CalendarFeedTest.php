<?php

namespace Tests\Feature;

use App\Http\Controllers\CalendarFeedController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/calender/{place}/public.ics, /api/calender/{place}/admin/{token}.ics and /api/calender/feeds
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 */
class CalendarFeedTest extends TestCase
{
    use DatabaseTransactions;

    private int $place;
    private int $terms;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.calendar.token' => 'secret123']);
        $this->place = DB::table('places')->insertGetId(['name' => 'Testlokal', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test']);
        $this->terms = DB::table('terms')->insertGetId(['content' => 'test terms']);
    }

    public function test_public_feed_lists_bookable_days_only(): void
    {
        $available = $this->period(now()->addDays(10), now()->addDays(11));
        $pending = $this->period(now()->addDays(12), now()->addDays(12), 'pending');
        $booked = $this->period(now()->addDays(13), now()->addDays(13), 'booked');
        $passed = $this->period(now()->subDays(3), now()->subDays(3));

        $otherPlace = DB::table('places')->insertGetId(['name' => 'Annan', 'description' => '', 'street' => 'Gata 2', 'zipcode' => 12345, 'city' => 'Test']);
        $otherPeriod = DB::table('periods')->insertGetId(['places_id' => $otherPlace, 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(10)->toDateString()]);

        $response = $this->get("/api/calender/{$this->place}/public.ics")->assertOk();
        $this->assertStringStartsWith('text/calendar', $response->headers->get('Content-Type'));
        $ics = $response->getContent();

        $this->assertStringContainsString("UID:period-{$available}@hgf-tuve.se", $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:' . now()->addDays(10)->format('Ymd'), $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:' . now()->addDays(12)->format('Ymd'), $ics);
        $this->assertStringContainsString('SUMMARY:Ledig: Testlokal', $ics);
        foreach ([$pending, $booked, $passed, $otherPeriod] as $period) {
            $this->assertStringNotContainsString("UID:period-{$period}@", $ics);
        }
        $this->assertStringNotContainsString('Test Person', $ics, 'no personal data');
    }

    public function test_admin_feed_lists_booked_bookings_with_details(): void
    {
        $this->period(now()->addDays(13), now()->addDays(13), 'booked');
        $this->period(now()->addDays(14), now()->addDays(14), 'pending');

        // Unfolded: long lines are split at 75 octets.
        $token = CalendarFeedController::adminToken($this->place);
        $ics = str_replace("\r\n ", '', $this->get("/api/calender/{$this->place}/admin/{$token}.ics")->assertOk()->getContent());

        $this->assertSame(1, substr_count($ics, 'BEGIN:VEVENT'), 'only the booked one');
        $this->assertStringContainsString('SUMMARY:Bokad: Test Person\, lgh 466', $ics);
        $this->assertStringContainsString('Telefon: 0701234567', $ics);
    }

    public function test_admin_feed_with_wrong_or_no_token_is_404(): void
    {
        $token = CalendarFeedController::adminToken($this->place);
        $this->get("/api/calender/{$this->place}/admin/0123abcd.ics")->assertNotFound();

        // Another place's token doesn't work for this place.
        $otherPlace = DB::table('places')->insertGetId(['name' => 'Annan', 'description' => '', 'street' => 'Gata 2', 'zipcode' => 12345, 'city' => 'Test']);
        $this->get("/api/calender/{$otherPlace}/admin/{$token}.ics")->assertNotFound();

        config(['services.calendar.token' => null]);
        $this->get("/api/calender/{$this->place}/admin/{$token}.ics")->assertNotFound();
    }

    public function test_feeds_tells_admins_both_urls(): void
    {
        $this->actingAsWpUser();

        $feed = collect($this->getJson('/api/calender/feeds')->assertOk()->json())->firstWhere('places_id', $this->place);

        $this->assertSame('Testlokal', $feed['place']);
        $this->assertSame(url("/api/calender/{$this->place}/public.ics"), $feed['public']);
        $this->assertSame(url("/api/calender/{$this->place}/admin/" . CalendarFeedController::adminToken($this->place) . '.ics'), $feed['admin']);
    }

    private function period(\DateTimeInterface $start, \DateTimeInterface $end, ?string $bookingStatus = null): int
    {
        $period = DB::table('periods')->insertGetId([
            'places_id' => $this->place, 'start_date' => $start->format('Y-m-d'), 'end_date' => $end->format('Y-m-d'),
        ]);
        if ($bookingStatus) {
            DB::table('bookings')->insert([
                'terms_id' => $this->terms, 'periods_id' => $period, 'status' => $bookingStatus, 'description' => 'test',
                'name' => 'Test Person', 'phone' => 701234567, 'email' => 'test@example.com',
                'street' => 'Gata 1', 'apartment' => 466, 'zipcode' => 12345, 'city' => 'Test',
            ]);
        }
        return $period;
    }
}
