<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: matches the table that was created by hand on production.
 * Skipped when the table already exists, so it is safe to run there.
 *
 * A period is a bookable date range for a place, created in advance by an admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('periods')) {
            return;
        }

        Schema::create('periods', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->id();
            $table->unsignedBigInteger('places_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('created_at')->useCurrent();

            $table->index('places_id', 'places_id');
            $table->foreign('places_id', 'periods_ibfk_1')->references('id')->on('places');
        });
    }

    public function down(): void
    {
        // Intentionally empty: on production up() is skipped because the table already existed,
        // so a rollback must never drop it (and its data). Drop it by hand if you really mean to.
    }
};
