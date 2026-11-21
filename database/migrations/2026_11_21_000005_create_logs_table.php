<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change log: one row per change to a booking, period, place or terms.
 *
 * Also covers admin decisions on bookings: who approved/rejected (wp_users_id),
 * when (created_at) and the reply sent to the booker (message).
 *
 * No foreign keys on purpose:
 * - wp_users_id points to WordPress' lh_users, which WordPress manages on its own.
 * - table_name/row_id is a loose reference, so log rows for different tables share one table.
 * The booking cleanup must delete log rows for the bookings it removes, since `changes`
 * may contain personal data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 64);
            $table->unsignedBigInteger('row_id');
            $table->string('action', 32)->comment('e.g. created, updated, booked, rejected, unbooked, deleted, deposit_paid, deposit_returned');
            $table->unsignedBigInteger('wp_users_id')->nullable()->comment('lh_users.ID of the admin, NULL for the public booker or the system');
            $table->json('changes')->nullable()->comment('{"column": [old, new], ...}');
            $table->text('message')->nullable()->comment('free text, e.g. the reply sent to the booker');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['table_name', 'row_id']);
            $table->index('wp_users_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
