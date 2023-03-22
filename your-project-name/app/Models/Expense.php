<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;
    protected $casts = [
        'id' => 'integer',
        'order_id' => 'integer',
        'restaurant_id' => 'integer',
        'amount' => 'float',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];


    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class,'restaurant_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
