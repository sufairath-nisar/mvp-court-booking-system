<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'location',
        'sport_type',
        'hourly_rate',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'is_active'   => 'boolean',
        ];
    }

    /**
     * A court has many time slots.
     */
    public function slots(): HasMany
    {
        return $this->hasMany(CourtSlot::class);
    }

    /**
     * A court has many bookings.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Scope a query to only active courts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
