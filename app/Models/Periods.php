<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property int $id BIGINT UNSIGNED AUTO_INCREMENT
 * @property int $places_id BIGINT UNSIGNED NOT NULL,
 * @property string $start_date DATE NOT NULL,
 * @property string $end_date DATE NOT NULL,
 * @property \DateTimeImmutable $created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
 * @property ?\DateTimeImmutable $deleted_at TIMESTAMP NULL, soft delete
 * @property-read Places $place
 * @property-read Collection<Bookings> $bookings
 */
class Periods extends Model
{
    use SoftDeletes;

    protected $table = 'periods';

    public $timestamps = false;
    protected $casts = [
        'created_at' => 'immutable_datetime',
        'deleted_at' => 'immutable_datetime',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Places::class, 'places_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Bookings::class, 'periods_id');
    }
}
