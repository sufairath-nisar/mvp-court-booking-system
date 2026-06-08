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
     * @var array<int, string>
     */
    protected $fillable = [
        'court_id',
        'date',
        'start_time',
        'end_time',
        'is_booked',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date'      => 'date:Y-m-d',
            'is_booked' => 'boolean',
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
     * A slot can have many bookings over its lifetime (e.g. booked then cancelled then re-booked).
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'slot_id');
    }

    /**
     * Scope a query to only slots that are not yet booked.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_booked', false);
    }
}
