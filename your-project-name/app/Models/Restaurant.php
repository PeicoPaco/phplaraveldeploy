<?php

namespace App\Models;

use App\Models\Vendor;
use App\Scopes\ZoneScope;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    protected $dates = ['opening_time', 'closeing_time'];

    protected $fillable = ['food_section','status'];

    protected $casts = [
        'minimum_order' => 'float',
        'comission' => 'float',
        'tax' => 'float',
        'delivery_charge' => 'float',
        'schedule_order'=>'boolean',
        'free_delivery'=>'boolean',
        'vendor_id'=>'integer',
        'status'=>'integer',
        'delivery'=>'boolean',
        'take_away'=>'boolean',
        'zone_id'=>'integer',
        'food_section'=>'boolean',
        'reviews_section'=>'boolean',
        'active'=>'boolean',
        'gst_status'=>'boolean',
        'pos_system'=>'boolean',
        'self_delivery_system'=>'integer',
        'open'=>'integer',
        'gst_code'=>'string',
        'off_day'=>'string',
        'gst'=>'string',
        'veg'=>'integer',
        'non_veg'=>'integer',
        'minimum_shipping_charge'=>'float',
        'per_km_shipping_charge'=>'float',
        'maximum_shipping_charge'=>'float',
        'cuisine_id'=>'integer',
    ];

    protected $appends = ['gst_status','gst_code'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'gst'
    ];

    public function transaction()
    {
        return $this->hasMany(OrderTransaction::class,'vendor_id','vendor_id');
    }
    public function coupon()
    {
        return $this->hasMany(Coupon::class,'restaurant_id');
    }

    public function restaurant_sub()
    {
        return $this->hasOne(RestaurantSubscription::class)->where('status',1)->latest();
    }

    public function restaurant_subs()
    {
        return $this->hasMany(RestaurantSubscription::class,'restaurant_id');
    }
    public function restaurant_sub_trans()
    {
        return $this->hasOne(SubscriptionTransaction::class)->latest();
    }
    public function restaurant_sub_update_application()
    {
        return $this->hasOne(RestaurantSubscription::class)->latest();
    }


    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function foods()
    {
        return $this->hasMany(Food::class);
    }

    public function schedules()
    {
        return $this->hasMany(RestaurantSchedule::class)->orderBy('opening_time');
    }

    public function deliverymen()
    {
        return $this->hasMany(DeliveryMan::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function discount()
    {
        return $this->hasOne(Discount::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function itemCampaigns()
    {
        return $this->hasMany(ItemCampaign::class);
    }

    public function reviews()
    {
        return $this->hasManyThrough(Review::class, Food::class);
    }

    public function getScheduleOrderAttribute($value)
    {
        return (boolean)(\App\CentralLogics\Helpers::schedule_order()?$value:0);
    }
    public function getRatingAttribute($value)
    {
        $ratings = json_decode($value, true);
        $rating5 = $ratings?$ratings[5]:0;
        $rating4 = $ratings?$ratings[4]:0;
        $rating3 = $ratings?$ratings[3]:0;
        $rating2 = $ratings?$ratings[2]:0;
        $rating1 = $ratings?$ratings[1]:0;
        return [$rating5, $rating4, $rating3, $rating2, $rating1];
    }

    public function getGstStatusAttribute()
    {
        return (boolean)($this->gst?json_decode($this->gst, true)['status']:0);
    }

    public function getGstCodeAttribute()
    {
        return (string)($this->gst?json_decode($this->gst, true)['code']:'');
    }

    public function scopeDelivery($query)
    {
        $query->where('delivery',1);
    }

    public function scopeTakeaway($query)
    {
        $query->where('take_away',1);
    }

    public function scopeActive($query)
    {
        if(!\App\CentralLogics\Helpers::commission_check()){
            $query = $query->where('restaurant_model','!=','commission');
        }
        return $query->where('status', 1);
    }

    public function scopeOpened($query)
    {
        return $query->where('active', 1);
    }

    public function scopeWithOpen($query,$longitude,$latitude)
    {
        $query->selectRaw('*, IF(((select count(*) from `restaurant_schedule` where `restaurants`.`id` = `restaurant_schedule`.`restaurant_id` and `restaurant_schedule`.`day` = '.now()->dayOfWeek.' and `restaurant_schedule`.`opening_time` < "'.now()->format('H:i:s').'" and `restaurant_schedule`.`closing_time` >"'.now()->format('H:i:s').'") > 0), true, false) as open,ST_Distance_Sphere(point(longitude, latitude),point('.$longitude.', '.$latitude.')) as distance');
    }

    public function scopeWeekday($query)
    {
        return $query->where('off_day', 'not like', "%".now()->dayOfWeek."%");
    }

    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
    }

    public function scopeType($query, $type)
    {
        if($type == 'veg')
        {
            return $query->where('veg', true);
        }
        else if($type == 'non_veg')
        {
            return $query->where('non_veg', true);
        }

        return $query;

    }
    public function scopeRestaurantModel($query, $type)
    {
        if($type == 'commission')
        {
            return $query->where('restaurant_model', 'commission');
        }
        else if($type == 'subscribed')
        {
            return $query->where('restaurant_model', 'subscription');
        }
        else if($type == 'unsubscribed')
        {
            return $query->where('restaurant_model', 'unsubscribed');
        }
        return $query;
    }

    public function scopeCuisine($query, $cuisine_id)
    {
        if($cuisine_id != 'all'){
            return $query->whereHas('cuisine', function ($query) use ($cuisine_id){
                $query->where('cuisine_restaurant.cuisine_id', $cuisine_id);
            });
        }
            return $query;
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($restaurant) {
            $restaurant->slug = $restaurant->generateSlug($restaurant->name);
            $restaurant->save();
        });
    }
    private function generateSlug($name)
    {
        $slug = Str::slug($name);
        if ($max_slug = static::where('slug', 'like',"{$slug}%")->latest('id')->value('slug')) {

            if($max_slug == $slug) return "{$slug}-2";

            $max_slug = explode('-',$max_slug);
            $count = array_pop($max_slug);
            if (isset($count) && is_numeric($count)) {
                $max_slug[]= ++$count;
                return implode('-', $max_slug);
            }
        }
        return $slug;
    }

    public function cuisine()
    {
        return $this->belongsToMany(Cuisine::class);
    }
}
