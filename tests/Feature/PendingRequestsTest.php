<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/request/pending
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 */
class PendingRequestsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_requests_tell_if_their_period_is_already_booked(): void
    {
        $place = DB::table('places')->insertGetId(['name' => 'Test', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test']);
        $terms = DB::table('terms')->insertGetId(['content' => 'test terms']);
        $bookedPeriod = DB::table('periods')->insertGetId(['places_id' => $place, 'start_date' => '2030-01-01', 'end_date' => '2030-01-01']);
        $freePeriod = DB::table('periods')->insertGetId(['places_id' => $place, 'start_date' => '2030-01-02', 'end_date' => '2030-01-02']);

        $booking = fn (int $period, string $status) => DB::table('bookings')->insertGetId([
            'terms_id' => $terms, 'periods_id' => $period, 'status' => $status, 'description' => 'test',
            'name' => 'Test Person', 'phone' => 701234567, 'email' => 'test@example.com',
            'street' => 'Gata 1', 'apartment' => 466, 'zipcode' => 12345, 'city' => 'Test',
        ]);
        $booking($bookedPeriod, 'booked');
        $colliding = $booking($bookedPeriod, 'pending');
        $free = $booking($freePeriod, 'pending');
        $booking($freePeriod, 'rejected');

        $pending = collect($this->getJson('/api/request/pending')->assertOk()->json())->keyBy('id');

        $this->assertTrue($pending[$colliding]['period_booked']);
        $this->assertFalse($pending[$free]['period_booked']);
        $this->assertSame('2030-01-01', $pending[$colliding]['period']['start_date']);
    }
}
