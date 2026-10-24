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
        'total_amount',
        'passengers',
        'notes',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'passengers'   => 'array',
    ];

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
