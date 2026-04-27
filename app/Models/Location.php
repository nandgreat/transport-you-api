<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'state_id',
        'status_id',
    ];

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
