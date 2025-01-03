<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id BIGINT UNSIGNED AUTO_INCREMENT
 * @property int $terms_id BIGINT UNSIGNED NOT NULL,
 * @property int $periods_id BIGINT UNSIGNED NOT NULL,
 * @property string $status ENUM ('pending', 'booked', 'unbooked', 'rejected', 'deleted') NOT NULL,
 * @property string $description TEXT NOT NULL,
 * @property string $name VARCHAR(255) NOT NULL,
 * @property int $phone INT NOT NULL,
 * @property string $email VARCHAR(255) NOT NULL,
 * @property string $street VARCHAR(255) NOT NULL,
 * @property int $apartment INT NOT NULL,
 * @property int $zipcode INT UNSIGNED NOT NULL,
 * @property string $city VARCHAR(255) NOT NULL,
 * @property \DateTimeImmutable $created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
 * @property \DateTimeImmutable $update_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL ON UPDATE CURRENT_TIMESTAMP(),
 */

class Bookings extends Model
{
    protected $table = 'bookings';

    public $timestamps = false;
    protected $casts = [
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime'
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Periods::class, 'periods_id');
    }


    public function term(): BelongsTo
    {
        return $this->belongsTo(Terms::class, 'terms_id');
    }
}
