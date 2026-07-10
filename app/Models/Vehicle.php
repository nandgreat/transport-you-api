<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'vehicle_type_id',
        'registration_number',
        'status',
        'image_url',
    ];

    protected $casts = [
        'vehicle_type_id' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
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
