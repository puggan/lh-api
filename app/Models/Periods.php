<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
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
    use LogsChanges;
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

    /**
     * The status shown in the calendar: "Booked", "Passed" (started and not booked), "Pending"
     * (has a request) or "Available" (bookable). Rejected, unbooked and deleted bookings don't count.
     * Load the bookings relation first when doing this for many periods.
     */
    public function status(): string
    {
        if ($this->bookings->contains('status', 'booked')) {
            return 'Booked';
        }
        if ($this->start_date <= now()->toDateString()) {
            return 'Passed';
        }
        if ($this->bookings->contains('status', 'pending')) {
            return 'Pending';
        }
        return 'Available';
    }
}
