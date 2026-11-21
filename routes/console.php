<?php

use App\Actions\CleanupOldBookings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bookings:cleanup {--dry-run : Only count, delete nothing}', function () {
    $result = CleanupOldBookings::run((bool)$this->option('dry-run'), source: 'command');
    $verb = $result['dry_run'] ? 'Would delete' : 'Deleted';
    $this->info("{$verb} {$result['bookings']} booking(s) and {$result['logs']} log row(s) for periods that ended before {$result['cutoff']}.");
})->purpose('Delete bookings for periods that ended more than ' . CleanupOldBookings::KEEP_MONTHS . ' month(s) ago');

// Needs a cron entry on the server: * * * * * cd /var/www/lh-api && php artisan schedule:run
// If the scheduler is not running, POST /api/request/cleanup does the same.
Schedule::command('bookings:cleanup')->dailyAt('03:00');
