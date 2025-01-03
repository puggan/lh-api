<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id BIGINT UNSIGNED AUTO_INCREMENT
 * @property string $name VARCHAR(255) NOT NULL,
 * @property string $description TEXT NOT NULL,
 * @property string $street VARCHAR(255) NOT NULL,
 * @property int $zipcode INT UNSIGNED NOT NULL,
 * @property string $city VARCHAR(255) NOT NULL,
 * @property \DateTimeImmutable $created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL
 */

class Places extends Model
{
    protected $table = 'places';

    public $timestamps = false;
    protected $casts = [
        'created_at' => 'immutable_datetime',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(Periods::class, 'places_id');
    }
}
