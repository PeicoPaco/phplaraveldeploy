<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $casts = [
        'id' => 'integer',
        'status' => 'integer',
        'extra_charges' => 'float',
        'starting_coverage_area' => 'float',
        'maximum_coverage_area' => 'float',
    ];

    public function delivery_man()
    {
        return $this->hasOne(DeliveryMan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
