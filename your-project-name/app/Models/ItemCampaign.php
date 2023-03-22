<?php

namespace App\Models;

use App\Scopes\ZoneScope;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemCampaign extends Model
{
    use HasFactory;

    protected $dates = ['created_at', 'updated_at', 'start_date', 'end_date', 'start_time', 'end_time'];

    protected $casts = [
        'tax' => 'float',
        'price' => 'float',
        'discount' => 'float',
        'status' => 'integer',
        'restaurant_id' => 'integer',
        'category_id' => 'integer',
        'veg' => 'integer',
    ];

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orderdetails()
    {
        return $this->hasMany(OrderDetail::class)->latest();
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }

    public function scopeRunning($query)
    {
        return $query->whereDate('end_date', '>=', date('Y-m-d'));
    }

    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                return $query->where('locale', app()->getLocale());
            }]);
        });
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($itemcampaign) {
            $itemcampaign->slug = $itemcampaign->generateSlug($itemcampaign->title);
            $itemcampaign->save();
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

}
