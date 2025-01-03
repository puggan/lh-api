<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id BIGINT UNSIGNED AUTO_INCREMENT
 * @property string $content TEXT NOT NULL,
 * @property \DateTimeImmutable $created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL
 */
class Terms extends Model
{
    protected $table = 'terms';

    public $timestamps = false;
    protected $casts = [
        'created_at' => 'immutable_datetime',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Bookings::class, 'terms_id');
    }
}
