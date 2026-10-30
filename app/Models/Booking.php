<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'schedule_id',
        'return_booking_id',
        'reference',
        'total_amount',
        'passengers',
        'notes',
        'status',
        'expires_at',
    ];

    /**
     * Generate the unique booking reference ("special booking id") at the point
     * of booking if the caller did not supply one.
     */
    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->reference)) {
                $booking->reference = static::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'TY-' . strtoupper(\Illuminate\Support\Str::random(8));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    protected $casts = [
        'total_amount' => 'decimal:2',
        'passengers'   => 'array',
        'expires_at'   => 'datetime',
    ];

    /**
     * Bookings that currently occupy seats: confirmed ones, plus pending holds
     * that have not yet expired. Expired, unpaid holds release their seats.
     */
    public function scopeHoldingSeats($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'confirmed')
              ->orWhere(function ($q) {
                  $q->where('status', 'pending')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
              });
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function returnBooking()
    {
        return $this->belongsTo(Booking::class, 'return_booking_id');
    }

    public function outboundBooking()
    {
        return $this->hasOne(Booking::class, 'return_booking_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
