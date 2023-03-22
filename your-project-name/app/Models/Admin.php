<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    public function role(){
        return $this->belongsTo(AdminRole::class,'role_id');
    }

    public function scopeZone($query)
    {
        if(isset(auth('admin')->user()->zone_id))
        {
            return $query->where('zone_id', auth('admin')->user()->zone_id);
        }
        return $query;
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class,'admin_id', 'id');
    }
}
