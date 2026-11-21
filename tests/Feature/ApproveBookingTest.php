<?php

namespace Tests\Feature;

use App\Models\Logs;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POST /api/request/approve
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 */
class ApproveBookingTest extends TestCase
{
    use DatabaseTransactions;

    private int $period;
    private int $terms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsWpUser();

        $place = DB::table('places')->insertGetId(['name' => 'Test', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test', 'deposit' => 500]);
        $this->terms = DB::table('terms')->insertGetId(['content' => 'test terms']);
        $this->period = DB::table('periods')->insertGetId(['places_id' => $place, 'start_date' => '2030-01-01', 'end_date' => '2030-01-01']);
    }

    public function test_approve_books_the_period_copies_the_deposit_and_logs_it(): void
    {
        $booking = $this->booking();

        $this->postJson('/api/request/approve', ['bookings_id' => $booking])
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->assertDatabaseHas('bookings', ['id' => $booking, 'status' => 'booked', 'deposit' => 500]);
        $log = Logs::query()->where('table_name', '=', 'bookings')->where('row_id', '=', $booking)->latest('id')->firstOrFail();
        $this->assertSame('booked', $log->action);
        $this->assertSame(['pending', 'booked'], $log->changes['status']);
        $this->assertSame([null, 500], $log->changes['deposit']);
    }

    public function test_second_booking_for_the_same_period_is_409(): void
    {
        $first = $this->booking();
        $second = $this->booking();
        $this->postJson('/api/request/approve', ['bookings_id' => $first])->assertOk();

        $this->postJson('/api/request/approve', ['bookings_id' => $second])
            ->assertStatus(409)
            ->assertJson(['ok' => false]);

        $this->assertDatabaseHas('bookings', ['id' => $second, 'status' => 'pending', 'deposit' => null]);
    }

    public function test_unknown_booking_is_404(): void
    {
        $this->postJson('/api/request/approve', ['bookings_id' => PHP_INT_MAX])
            ->assertNotFound();
    }

    private function booking(): int
    {
        return DB::table('bookings')->insertGetId([
            'terms_id' => $this->terms, 'periods_id' => $this->period, 'status' => 'pending', 'description' => 'test',
            'name' => 'Test Person', 'phone' => 701234567, 'email' => 'test@example.com',
            'street' => 'Gata 1', 'apartment' => 1101, 'zipcode' => 12345, 'city' => 'Test',
        ]);
    }
}
