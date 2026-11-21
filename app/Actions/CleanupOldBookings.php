<?php

namespace App\Actions;

use App\Models\Bookings;
use App\Models\Logs;
use App\Models\Periods;
use Illuminate\Support\Facades\DB;

/**
 * Deletes bookings (personal data) once they are no longer needed.
 *
 * Lokalen is not rented out; the booker leaves a deposit that is returned after the event.
 * After that the booking has no use, so bookings whose period ended more than
 * KEEP_MONTHS ago are deleted, together with their rows in `logs` (which may contain
 * personal data in `changes`). Periods are kept, so the calendar history stays.
 *
 * Used by the scheduled command `bookings:cleanup` (routes/console.php)
 * and by POST /api/request/cleanup, in case the scheduler is not running.
 */
class CleanupOldBookings
{
    public const KEEP_MONTHS = 1;

    /** Bookings for periods that ended before this date are deleted. */
    public static function cutoff(): string
    {
        return now()->subMonthsNoOverflow(self::KEEP_MONTHS)->toDateString();
    }

    /**
     * @param bool $dryRun only count, delete nothing
     * @param ?int $wpUsersId the admin who started it (lh_users.ID), null for the scheduler
     * @param string $source where it was started from, stored in the log message
     * @return array{cutoff: string, dry_run: bool, bookings: int, logs: int}
     */
    public static function run(bool $dryRun = false, ?int $wpUsersId = null, string $source = 'manual'): array
    {
        $cutoff = self::cutoff();

        return DB::transaction(function () use ($cutoff, $dryRun, $wpUsersId, $source) {
            // Soft deleted periods count too: their bookings are just as old.
            $oldPeriodIds = Periods::withTrashed()->where('end_date', '<', $cutoff)->select('id');
            $bookingIds = Bookings::query()->whereIn('periods_id', $oldPeriodIds)->pluck('id');
            $logsQuery = Logs::query()->where('table_name', '=', 'bookings')->whereIn('row_id', $bookingIds);

            $result = [
                'cutoff' => $cutoff,
                'dry_run' => $dryRun,
                'bookings' => $bookingIds->count(),
                'logs' => $logsQuery->count(),
            ];

            if ($dryRun || $bookingIds->isEmpty()) {
                return $result;
            }

            $logsQuery->delete();
            Bookings::query()->whereIn('id', $bookingIds)->delete();

            // A summary without personal data. row_id 0: it is about many bookings, not one.
            $log = new Logs();
            $log->table_name = 'bookings';
            $log->row_id = 0;
            $log->action = 'cleanup';
            $log->wp_users_id = $wpUsersId;
            $log->changes = ['deleted_bookings' => $result['bookings'], 'deleted_logs' => $result['logs'], 'cutoff' => $cutoff];
            $log->message = "Deleted bookings for periods that ended before {$cutoff} ({$source})";
            $log->save();

            return $result;
        });
    }
}
