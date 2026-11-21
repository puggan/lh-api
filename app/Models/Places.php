<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id BIGINT UNSIGNED AUTO_INCREMENT
 * @property string $name VARCHAR(255) NOT NULL,
 * @property string $description TEXT NOT NULL,
 * @property string $street VARCHAR(255) NOT NULL,
 * @property int $zipcode INT UNSIGNED NOT NULL,
 * @property string $city VARCHAR(255) NOT NULL,
 * @property int $deposit INT UNSIGNED DEFAULT 0 NOT NULL, SEK
 * @property ?int $admin_phone BIGINT UNSIGNED NULL, Swedish number without the leading 0, for messages to the admin
 * @property \DateTimeImmutable $created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL
 * @property-read Collection<Periods> $periods
 */

class Places extends Model
{
    use LogsChanges;

    protected $table = 'places';

    public $timestamps = false;
    protected $casts = [
        'created_at' => 'immutable_datetime',
    ];

    /** GET /api/places is public. */
    protected $hidden = ['admin_phone'];

    public function periods(): HasMany
    {
        return $this->hasMany(Periods::class, 'places_id');
    }
}
