<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'logo_url',
        'status',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
