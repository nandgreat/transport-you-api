<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'route_id',
        'vehicle_type_id',
        'start_date',
        'end_date',
        'days_of_week',
        'departure_time',
        'arrival_time',
        'fare',
        'status',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'days_of_week' => 'array',
        'fare'         => 'decimal:2',
    ];

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

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
