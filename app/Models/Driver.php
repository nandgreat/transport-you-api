<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'licence_number',
        'status',
        'availability',
        'trips_completed',
    ];

    protected $casts = [
        'trips_completed' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    /** Trips this driver has been dispatched on and that have completed. */
    public function completedTrips()
    {
        return $this->hasMany(Schedule::class)->where('trip_status', 'completed');
    }
}
