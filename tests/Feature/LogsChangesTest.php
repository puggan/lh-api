<?php

namespace Tests\Feature;

use App\Models\Bookings;
use App\Models\Logs;
use App\Models\Periods;
use App\Models\Places;
use App\Models\Terms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The LogsChanges trait: model changes are written to `logs`.
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 */
class LogsChangesTest extends TestCase
{
    use DatabaseTransactions;

    private Places $place;
    private Terms $terms;
    private Periods $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->place = new Places();
        $this->place->forceFill(['name' => 'Test', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test'])->save();
        $this->terms = new Terms();
        $this->terms->forceFill(['content' => 'test terms'])->save();
        $this->period = new Periods();
        $this->period->forceFill(['places_id' => $this->place->id, 'start_date' => '2030-01-01', 'end_date' => '2030-01-01'])->save();
    }

    public function test_created_is_logged_with_new_values(): void
    {
        $log = $this->lastLog('places', $this->place->id);

        $this->assertSame('created', $log->action);
        $this->assertSame([null, 'Test'], $log->changes['name']);
        $this->assertSame([null, 12345], $log->changes['zipcode']);
        $this->assertNull($log->wp_users_id, 'nobody is logged in');
    }

    public function test_status_change_is_logged_as_the_new_status(): void
    {
        $booking = $this->booking();
        $booking->status = 'rejected';
        $booking->save();

        $log = $this->lastLog('bookings', $booking->id);
        $this->assertSame('rejected', $log->action);
        $this->assertSame(['status' => ['pending', 'rejected']], $log->changes);
    }

    public function test_other_update_is_logged_with_only_the_changed_columns(): void
    {
        $booking = $this->booking();
        $booking->phone = 709999999;
        $booking->save();

        $log = $this->lastLog('bookings', $booking->id);
        $this->assertSame('updated', $log->action);
        $this->assertSame(['phone' => [701234567, 709999999]], $log->changes);
    }

    public function test_save_without_changes_writes_nothing(): void
    {
        $before = Logs::query()->count();
        $this->period->save();
        $this->assertSame($before, Logs::query()->count());
    }

    public function test_soft_delete_and_restore_are_logged(): void
    {
        $this->period->delete();
        $deleted = $this->lastLog('periods', $this->period->id);
        $this->assertSame('deleted', $deleted->action);
        $this->assertSame(['deleted_at'], array_keys($deleted->changes));
        $this->assertNull($deleted->changes['deleted_at'][0]);
        $this->assertNotNull($deleted->changes['deleted_at'][1]);

        $this->period->restore();
        $restored = $this->lastLog('periods', $this->period->id);
        $this->assertSame('restored', $restored->action);
        $this->assertNull($restored->changes['deleted_at'][1]);
    }

    public function test_hard_delete_is_logged_with_old_values(): void
    {
        $this->terms->delete();

        $log = $this->lastLog('terms', $this->terms->id);
        $this->assertSame('deleted', $log->action);
        $this->assertSame(['test terms', null], $log->changes['content']);
    }

    private function booking(): Bookings
    {
        $booking = new Bookings();
        $booking->forceFill([
            'terms_id' => $this->terms->id, 'periods_id' => $this->period->id, 'status' => 'pending', 'description' => 'test',
            'name' => 'Test Person', 'phone' => 701234567, 'email' => 'test@example.com',
            'street' => 'Gata 1', 'apartment' => 1101, 'zipcode' => 12345, 'city' => 'Test',
        ])->save();
        return $booking;
    }

    private function lastLog(string $table, int $id): Logs
    {
        /** @var Logs */
        return Logs::query()->where('table_name', '=', $table)->where('row_id', '=', $id)->latest('id')->firstOrFail();
    }
}
