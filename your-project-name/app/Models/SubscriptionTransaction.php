<?php

namespace App\Models;

use App\Scopes\ZoneScope;
use App\Scopes\RestaurantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionTransaction extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $casts = [
        'package_details' => 'array',
        'id'=> 'string',
        'chat'=>'integer',
        'review'=>'integer',
        'package_id'=>'integer',
        'restaurant_id'=>'integer',
        'status'=>'integer',
        'self_delivery'=>'integer',
        'max_order'=>'string',
        'max_product'=>'string',
        'payment_method'=>'string',
        'paid_amount'=>'float',
        'validity'=>'integer',

    ];

    public function restaurant()
    {
        return $this->hasOne(Restaurant::class,'id', 'restaurant_id');
    }
    public function package()
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id', 'id');
    }
    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
    }

}
