<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: matches the table that was created by hand on production.
 * Skipped when the table already exists, so it is safe to run there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('places')) {
            return;
        }

        Schema::create('places', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('street');
            $table->unsignedInteger('zipcode');
            $table->string('city');
            $table->timestamp('created_at')->useCurrent();

            $table->index('name', 'name');
        });
    }

    public function down(): void
    {
        // Intentionally empty: on production up() is skipped because the table already existed,
        // so a rollback must never drop it (and its data). Drop it by hand if you really mean to.
    }
};
