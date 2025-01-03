<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
    * @property int $id BIGINT UNSIGNED AUTO_INCREMENT
    * @property int $places_id BIGINT UNSIGNED NOT NULL,
    * @property int $start_date DATE NOT NULL,
    * @property int $end_date DATE NOT NULL,
    * @property \DateTimeImmutable $created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
 */
class Periods extends Model
{
protected $table = 'periods';

    public $timestamps = false;
    protected $casts = [
        'created_at' => 'immutable_datetime',
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
