<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * places.admin_phone: the Swedish phone number of the person who handles the place's bookings,
 * for messages to the admin (Communication::informAdmin). Stored like bookings.phone:
 * without the leading 0, e.g. 0701234567 -> 701234567.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_phone')->nullable()->after('deposit');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('admin_phone');
        });
    }
};
