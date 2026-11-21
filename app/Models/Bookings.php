<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property int $id BIGINT UNSIGNED AUTO_INCREMENT
 * @property int $terms_id BIGINT UNSIGNED NOT NULL,
 * @property int $periods_id BIGINT UNSIGNED NOT NULL,
 * @property string $status ENUM ('pending', 'booked', 'unbooked', 'rejected', 'deleted') NOT NULL,
 * @property string $description TEXT NOT NULL,
 * @property string $name VARCHAR(255) NOT NULL,
 * @property int $phone BIGINT NOT NULL,
 * @property string $email VARCHAR(255) NOT NULL,
 * @property string $street VARCHAR(255) NOT NULL,
 * @property int $apartment INT NOT NULL,
 * @property int $zipcode INT UNSIGNED NOT NULL,
 * @property string $city VARCHAR(255) NOT NULL,
 * @property ?int $deposit INT UNSIGNED NULL, SEK, copied from the place when booked
 * @property ?\DateTimeImmutable $deposit_paid_at TIMESTAMP NULL,
 * @property ?\DateTimeImmutable $deposit_returned_at TIMESTAMP NULL,
 * @property \DateTimeImmutable $created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
 * @property \DateTimeImmutable $updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL ON UPDATE CURRENT_TIMESTAMP(),
 * @property-read Periods $period
 * @property-read Terms $term
 */
class Bookings extends Model
{
    protected $table = 'bookings';

    public $timestamps = false;
    protected $casts = [
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'deposit_paid_at' => 'immutable_datetime',
        'deposit_returned_at' => 'immutable_datetime',
    ];

    public function period(): BelongsTo
    {
        // A booking keeps its period even if the period has been soft deleted.
        return $this->belongsTo(Periods::class, 'periods_id')->withTrashed();
    }


    public function term(): BelongsTo
    {
        return $this->belongsTo(Terms::class, 'terms_id');
    }
}
