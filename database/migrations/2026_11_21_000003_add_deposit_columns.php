<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lokalen is not rented out; the booker leaves a deposit that is returned afterwards.
 *
 * - places.deposit: the current deposit amount, in whole SEK.
 * - bookings.deposit: the amount that applied when the booking was made (the place's amount may change later).
 * - bookings.deposit_paid_at / deposit_returned_at: when the deposit was received and paid back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->unsignedInteger('deposit')->default(0)->after('city');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('deposit')->nullable()->after('city');
            $table->timestamp('deposit_paid_at')->nullable()->after('deposit');
            $table->timestamp('deposit_returned_at')->nullable()->after('deposit_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit', 'deposit_paid_at', 'deposit_returned_at']);
        });

        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('deposit');
        });
    }
};
