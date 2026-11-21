<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Many bookings may compete for one period, but only one of them may be 'booked'.
 *
 * MariaDB has no partial (filtered) unique index, so we use a generated column that
 * holds periods_id only while status = 'booked', and NULL otherwise.
 * A unique index ignores NULLs, so any number of pending/rejected rows are allowed,
 * but a second 'booked' row for the same period is refused by the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('booked_periods_id')
                ->nullable()
                ->storedAs("IF(`status` = 'booked', `periods_id`, NULL)")
                ->after('status');
            $table->unique('booked_periods_id', 'bookings_one_booked_per_period');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_one_booked_per_period');
            $table->dropColumn('booked_periods_id');
        });
    }
};
