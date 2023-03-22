<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cuisine extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'image'];
    protected $casts = [
        'id' => 'integer',
        'status' => 'integer',
    ];

    public function restaurants()
    {
        return $this->belongsToMany(Restaurant::class)->using('App\Models\Cuisine_restaurant');
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($cuisine) {
            $cuisine->slug = $cuisine->generateSlug($cuisine->name);
            $cuisine->save();
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
