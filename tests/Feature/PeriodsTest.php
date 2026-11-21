<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POST /api/periods and DELETE /api/periods/{id}
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 */
class PeriodsTest extends TestCase
{
    use DatabaseTransactions;

    private int $place;

    protected function setUp(): void
    {
        parent::setUp();
        $this->place = DB::table('places')->insertGetId(['name' => 'Test', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test']);
    }

    public function test_create_period(): void
    {
        $response = $this->postJson('/api/periods', ['places_id' => $this->place, 'start_date' => '2030-06-01', 'end_date' => '2030-06-02'])
            ->assertCreated()
            ->assertJson(['places_id' => $this->place, 'start_date' => '2030-06-01', 'end_date' => '2030-06-02', 'deleted_at' => null]);

        $this->assertDatabaseHas('logs', ['table_name' => 'periods', 'row_id' => $response->json('id'), 'action' => 'created']);
    }

    public function test_create_rejects_wrong_input(): void
    {
        $this->postJson('/api/periods', ['places_id' => PHP_INT_MAX, 'start_date' => now()->subDay()->toDateString(), 'end_date' => 'tomorrow'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['places_id', 'start_date', 'end_date']);

        $this->postJson('/api/periods', ['places_id' => $this->place, 'start_date' => '2030-06-02', 'end_date' => '2030-06-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_create_rejects_overlap_but_not_with_a_deleted_period(): void
    {
        $existing = $this->period('2030-06-01', '2030-06-03');
        $this->period('2030-07-01', '2030-07-01', deleted: true);

        $this->postJson('/api/periods', ['places_id' => $this->place, 'start_date' => '2030-06-03', 'end_date' => '2030-06-04'])
            ->assertStatus(422)
            ->assertJsonPath('errors.start_date.0', "Overlaps period {$existing} (2030-06-01 – 2030-06-03).");

        $this->postJson('/api/periods', ['places_id' => $this->place, 'start_date' => '2030-07-01', 'end_date' => '2030-07-01'])
            ->assertCreated();
    }

    public function test_delete_soft_deletes_and_logs(): void
    {
        $period = $this->period('2030-06-01', '2030-06-01');

        $this->deleteJson("/api/periods/{$period}")
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->assertSoftDeleted('periods', ['id' => $period]);
        $this->assertDatabaseHas('logs', ['table_name' => 'periods', 'row_id' => $period, 'action' => 'deleted']);

        $this->deleteJson("/api/periods/{$period}")->assertNotFound();
    }

    public function test_delete_with_a_pending_booking_is_409_but_a_rejected_one_is_fine(): void
    {
        $period = $this->period('2030-06-01', '2030-06-01');
        $terms = DB::table('terms')->insertGetId(['content' => 'test terms']);
        $booking = DB::table('bookings')->insertGetId([
            'terms_id' => $terms, 'periods_id' => $period, 'status' => 'pending', 'description' => 'test',
            'name' => 'Test Person', 'phone' => 701234567, 'email' => 'test@example.com',
            'street' => 'Gata 1', 'apartment' => 1101, 'zipcode' => 12345, 'city' => 'Test',
        ]);

        $this->deleteJson("/api/periods/{$period}")->assertStatus(409);
        $this->assertNotSoftDeleted('periods', ['id' => $period]);

        DB::table('bookings')->where('id', '=', $booking)->update(['status' => 'rejected']);
        $this->deleteJson("/api/periods/{$period}")->assertOk();
    }

    private function period(string $start, string $end, bool $deleted = false): int
    {
        return DB::table('periods')->insertGetId([
            'places_id' => $this->place, 'start_date' => $start, 'end_date' => $end,
            'deleted_at' => $deleted ? now() : null,
        ]);
    }
}
