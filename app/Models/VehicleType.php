<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_name',
        'image_url',
        'capacity',
        'vehicle_category_id',
        'status',
        'year',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'year'     => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
