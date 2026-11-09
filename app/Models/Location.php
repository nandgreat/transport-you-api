<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'external_ref',
        'name',
        'city',
        'address',
        'latitude',
        'longitude',
        'state_id',
        'status_id',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
