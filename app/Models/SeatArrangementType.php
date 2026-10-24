<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeatArrangementType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'number_of_seats',
        'arrangement_image',
        'description',
        'status',
    ];

    protected $casts = [
        'number_of_seats' => 'integer',
    ];

    public function vehicleTypes()
    {
        return $this->belongsToMany(VehicleType::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
