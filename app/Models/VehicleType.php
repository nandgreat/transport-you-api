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
        'vehicle_category_id',
        'status',
        'year',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function seatArrangementTypes()
    {
        return $this->belongsToMany(SeatArrangementType::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /**
     * The arrangement to use when a schedule on this type doesn't choose one:
     * the one flagged as default, else the first attached.
     */
    public function defaultSeatArrangementType(): ?SeatArrangementType
    {
        $arrangements = $this->seatArrangementTypes;
        return $arrangements->firstWhere('pivot.is_default', true) ?? $arrangements->first();
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
