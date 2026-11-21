<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/calender, the status of each period.
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 */
class CalenderTest extends TestCase
{
    use DatabaseTransactions;

    private int $place;
    private int $terms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->place = DB::table('places')->insertGetId(['name' => 'Test', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test']);
        $this->terms = DB::table('terms')->insertGetId(['content' => 'test terms']);
    }

    public function test_status_of_each_period(): void
    {
        $future = now()->addDays(10)->toDateString();
        $past = now()->subDays(10)->toDateString();

        $expected = [
            $this->period($future) => 'Available',
            $this->period($future, ['rejected', 'unbooked']) => 'Available',
            $this->period($future, ['rejected', 'pending']) => 'Pending',
            $this->period($future, ['rejected', 'pending', 'booked']) => 'Booked',
            $this->period($past) => 'Passed',
            $this->period($past, ['pending']) => 'Passed',
            $this->period($past, ['booked']) => 'Booked',
        ];

        $statuses = collect($this->getJson('/api/calender')->assertOk()->json())
            ->whereIn('id', array_keys($expected))
            ->pluck('status', 'id')
            ->all();

        $this->assertEquals($expected, $statuses);
    }

    /** @param list<string> $bookingStatuses */
    private function period(string $date, array $bookingStatuses = []): int
    {
        $period = DB::table('periods')->insertGetId(['places_id' => $this->place, 'start_date' => $date, 'end_date' => $date]);
        foreach ($bookingStatuses as $status) {
            DB::table('bookings')->insert([
                'terms_id' => $this->terms, 'periods_id' => $period, 'status' => $status, 'description' => 'test',
                'name' => 'Test Person', 'phone' => 701234567, 'email' => 'test@example.com',
                'street' => 'Gata 1', 'apartment' => 466, 'zipcode' => 12345, 'city' => 'Test',
            ]);
        }
        return $period;
    }
}
