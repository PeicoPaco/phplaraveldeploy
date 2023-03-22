<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPackage extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $casts = [
        'price'=>'float',
        'validity'=>'integer',
        'chat'=>'integer',
        'review'=>'integer',
        'package_id'=>'integer',
        'status'=>'integer',
        'pos'=>'integer',
        'default'=>'integer',
        'mobile_app'=>'integer',
        'total_package_renewed'=>'integer',
        'self_delivery'=>'integer',
        'restaurant_id'=>'integer',
        'max_order'=>'string',
        'max_product'=>'string',
    ];
    public function transactions()
    {
        return $this->hasMany(SubscriptionTransaction::class, 'package_id');
    }

}
