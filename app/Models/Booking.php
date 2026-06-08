<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'court_id',
        'slot_id',
        'booking_date',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_date' => 'date:Y-m-d',
            'status'       => BookingStatus::class,
        ];
    }

    /**
     * The booking belongs to a consumer (user).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The booking belongs to a court.
     */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * The booking belongs to a court slot.
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(CourtSlot::class, 'slot_id');
    }

    /**
     * Scope a query to only active (booked) bookings.
     */
    public function scopeBooked($query)
    {
        return $query->where('status', BookingStatus::BOOKED);
    }
}
