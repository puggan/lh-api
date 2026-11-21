<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id BIGINT UNSIGNED AUTO_INCREMENT
 * @property string $table_name VARCHAR(64) NOT NULL, e.g. 'bookings', 'periods'
 * @property int $row_id BIGINT UNSIGNED NOT NULL, id in table_name
 * @property string $action VARCHAR(32) NOT NULL, e.g. 'created', 'booked', 'rejected', 'deposit_returned'
 * @property ?int $wp_users_id BIGINT UNSIGNED NULL, lh_users.ID of the admin, NULL for the booker or the system
 * @property ?array $changes JSON NULL, {"column": [old, new], ...}
 * @property ?string $message TEXT NULL, e.g. the reply sent to the booker
 * @property \DateTimeImmutable $created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL
 */
class Logs extends Model
{
    protected $table = 'logs';

    public $timestamps = false;
    protected $casts = [
        'changes' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
