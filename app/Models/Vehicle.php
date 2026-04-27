<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'registration_number',
        'type',
        'capacity',
        'status',
        'image_url',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
