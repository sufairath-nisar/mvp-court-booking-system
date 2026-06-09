<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourtSlot extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * A slot is a RECURRING weekly window: (court, day_of_week, start, end). It has no
     * date — the date a consumer books lives on the booking (`bookings.booking_date`).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'court_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    /**
     * The slot belongs to a court.
     */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * A slot (recurring window) can have many bookings — one per date it's booked.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'slot_id');
    }
}
