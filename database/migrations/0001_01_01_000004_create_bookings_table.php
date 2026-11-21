<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: matches the table that was created by hand on production,
 * including the misspelled `update_at` column (renamed in a later migration).
 * Skipped when the table already exists, so it is safe to run there.
 *
 * A booking is a request for a period. Several requests may compete for the same period;
 * an admin approves one (status 'booked') and rejects the rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings')) {
            return;
        }

        Schema::create('bookings', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->id();
            $table->unsignedBigInteger('terms_id');
            $table->unsignedBigInteger('periods_id');
            $table->enum('status', ['pending', 'booked', 'unbooked', 'rejected', 'deleted']);
            $table->text('description');
            $table->string('name');
            $table->bigInteger('phone');
            $table->string('email');
            $table->string('street');
            $table->integer('apartment');
            $table->unsignedInteger('zipcode');
            $table->string('city');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('update_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('terms_id', 'terms_id');
            $table->index('periods_id', 'periods_id');
            $table->foreign('terms_id', 'bookings_ibfk_1')->references('id')->on('terms');
            $table->foreign('periods_id', 'bookings_ibfk_2')->references('id')->on('periods');
        });
    }

    public function down(): void
    {
        // Intentionally empty: on production up() is skipped because the table already existed,
        // so a rollback must never drop it (and its data). Drop it by hand if you really mean to.
    }
};
