<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\VehicleType;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'schedule_group_id',
        'return_schedule_id',
        'route_id',
        'vehicle_type_id',
        'departure_time',
        'arrival_time',
        'fare',
        'status',
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'fare' => 'decimal:2',
    ];

    public function scheduleGroup()
    {
        return $this->belongsTo(ScheduleGroup::class);
    }

    public function returnSchedule()
    {
        return $this->belongsTo(Schedule::class, 'return_schedule_id');
    }

    public function outboundSchedule()
    {
        return $this->hasOne(Schedule::class, 'return_schedule_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function availableSeats(): int
    {
        $capacity = $this->vehicleType?->capacity ?? 0;
        $booked   = $this->bookings()->whereIn('status', ['pending', 'confirmed'])->count();
        return max(0, $capacity - $booked);
    }
}
