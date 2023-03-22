<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Cuisine_restaurant extends Pivot
{
    use HasFactory;
    protected $guarder = ['id'];

    protected $casts = [
        'id'=>'integer',
        'cuisine_id'=>'integer',
        'restaurant_id'=>'integer'
    ];
}
