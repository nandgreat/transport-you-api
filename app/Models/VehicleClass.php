<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_class',
        'vehicle_class_image',
        'tenant_id',
        'status_id',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
