<?php

namespace App\Models;

use Facade\FlareClient\Time\Time;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeLog extends Model
{
    use HasFactory;

    public function deliveryman()
    {
        return $this->belongsTo(TimeLog::class, 'user_id', 'id');
    }
}
