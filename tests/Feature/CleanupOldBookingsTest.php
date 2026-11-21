<?php

namespace Tests\Feature;

use App\Actions\CleanupOldBookings;
use App\Models\Logs;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The cleanup of old bookings, via POST /api/request/cleanup and the bookings:cleanup command.
 *
 * Runs against the configured database inside a transaction that is rolled back after each test,
 * so existing data is untouched. Existing old bookings may also be counted, so the tests check
 * their own rows rather than exact totals.
 */
class CleanupOldBookingsTest extends TestCase
{
    use DatabaseTransactions;

    private int $oldBooking;
    private int $oldBookingOnDeletedPeriod;
    private int $cutoffDayBooking;
    private int $recentBooking;
    private int $oldPeriod;
    private int $oldBookingLog;

    protected function setUp(): void
    {
        parent::setUp();

        $place = DB::table('places')->insertGetId(['name' => 'Test', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test']);
        $terms = DB::table('terms')->insertGetId(['content' => 'test terms']);
        $cutoff = CleanupOldBookings::cutoff();

        $this->oldPeriod = $this->period($place, now()->subMonths(2)->toDateString());
        $deletedPeriod = $this->period($place, now()->subMonths(2)->toDateString(), deleted: true);
        $cutoffPeriod = $this->period($place, $cutoff);
        $recentPeriod = $this->period($place, now()->subDays(10)->toDateString());

        $this->oldBooking = $this->booking($terms, $this->oldPeriod);
        $this->oldBookingOnDeletedPeriod = $this->booking($terms, $deletedPeriod);
        $this->cutoffDayBooking = $this->booking($terms, $cutoffPeriod);
        $this->recentBooking = $this->booking($terms, $recentPeriod);

        $this->oldBookingLog = DB::table('logs')->insertGetId([
            'table_name' => 'bookings', 'row_id' => $this->oldBooking, 'action' => 'created',
            'changes' => json_encode(['name' => [null, 'Personal Data']]),
        ]);
    }

    public function test_dry_run_counts_but_deletes_nothing(): void
    {
        $summaries = Logs::query()->where('action', '=', 'cleanup')->count();

        $this->postJson('/api/request/cleanup?dry_run=1')
            ->assertOk()
            ->assertJson(['dry_run' => true, 'cutoff' => CleanupOldBookings::cutoff()])
            ->assertJsonPath('bookings', fn (int $n) => $n >= 2)
            ->assertJsonPath('logs', fn (int $n) => $n >= 1);

        $this->assertDatabaseHas('bookings', ['id' => $this->oldBooking]);
        $this->assertDatabaseHas('logs', ['id' => $this->oldBookingLog]);
        $this->assertSame($summaries, Logs::query()->where('action', '=', 'cleanup')->count());
    }

    public function test_endpoint_deletes_old_bookings_and_their_logs(): void
    {
        $this->postJson('/api/request/cleanup')
            ->assertOk()
            ->assertJson(['dry_run' => false])
            ->assertJsonPath('bookings', fn (int $n) => $n >= 2);

        $this->assertDatabaseMissing('bookings', ['id' => $this->oldBooking]);
        $this->assertDatabaseMissing('bookings', ['id' => $this->oldBookingOnDeletedPeriod]);
        $this->assertDatabaseMissing('logs', ['id' => $this->oldBookingLog]);

        // Kept: the period itself, a booking whose period ended exactly on the cutoff day, and recent bookings.
        $this->assertDatabaseHas('periods', ['id' => $this->oldPeriod]);
        $this->assertDatabaseHas('bookings', ['id' => $this->cutoffDayBooking]);
        $this->assertDatabaseHas('bookings', ['id' => $this->recentBooking]);

        /** @var Logs $summary */
        $summary = Logs::query()->where('action', '=', 'cleanup')->latest('id')->firstOrFail();
        $this->assertSame(0, $summary->row_id);
        $this->assertGreaterThanOrEqual(2, $summary->changes['deleted_bookings']);
        $this->assertStringContainsString('(endpoint)', $summary->message);
        $this->assertStringNotContainsString('Personal Data', json_encode($summary->changes));
    }

    public function test_command_deletes_old_bookings(): void
    {
        $this->artisan('bookings:cleanup')
            ->expectsOutputToContain('Deleted')
            ->assertSuccessful();

        $this->assertDatabaseMissing('bookings', ['id' => $this->oldBooking]);
        $this->assertDatabaseHas('bookings', ['id' => $this->recentBooking]);
    }

    public function test_nothing_to_delete_writes_no_summary_log(): void
    {
        CleanupOldBookings::run();
        $summaries = Logs::query()->where('action', '=', 'cleanup')->count();

        $result = CleanupOldBookings::run();

        $this->assertSame(0, $result['bookings']);
        $this->assertSame($summaries, Logs::query()->where('action', '=', 'cleanup')->count());
    }

    private function period(int $place, string $date, bool $deleted = false): int
    {
        return DB::table('periods')->insertGetId([
            'places_id' => $place, 'start_date' => $date, 'end_date' => $date,
            'deleted_at' => $deleted ? now() : null,
        ]);
    }

    private function booking(int $terms, int $period): int
    {
        return DB::table('bookings')->insertGetId([
            'terms_id' => $terms, 'periods_id' => $period, 'status' => 'pending', 'description' => 'test',
            'name' => 'Personal Data', 'phone' => 701234567, 'email' => 'test@example.com',
            'street' => 'Gata 1', 'apartment' => 1101, 'zipcode' => 12345, 'city' => 'Test',
        ]);
    }
}
