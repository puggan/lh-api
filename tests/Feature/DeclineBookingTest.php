<?php

namespace Tests\Feature;

use App\Models\Logs;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POST /api/request/decline
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 */
class DeclineBookingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_decline_rejects_the_booking_and_logs_it(): void
    {
        $place = DB::table('places')->insertGetId(['name' => 'Test', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test']);
        $terms = DB::table('terms')->insertGetId(['content' => 'test terms']);
        $period = DB::table('periods')->insertGetId(['places_id' => $place, 'start_date' => '2030-01-01', 'end_date' => '2030-01-01']);
        $booking = DB::table('bookings')->insertGetId([
            'terms_id' => $terms, 'periods_id' => $period, 'status' => 'pending', 'description' => 'test',
            'name' => 'Test Person', 'phone' => 701234567, 'email' => 'test@example.com',
            'street' => 'Gata 1', 'apartment' => 1101, 'zipcode' => 12345, 'city' => 'Test',
        ]);

        $this->postJson('/api/request/decline', ['bookings_id' => $booking])
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->assertDatabaseHas('bookings', ['id' => $booking, 'status' => 'rejected']);
        $log = Logs::query()->where('table_name', '=', 'bookings')->where('row_id', '=', $booking)->latest('id')->firstOrFail();
        $this->assertSame('rejected', $log->action);
    }

    public function test_unknown_booking_is_404(): void
    {
        $this->postJson('/api/request/decline', ['bookings_id' => PHP_INT_MAX])
            ->assertNotFound();
    }
}
