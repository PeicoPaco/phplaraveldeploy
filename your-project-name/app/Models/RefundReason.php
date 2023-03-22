<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundReason extends Model
{
    protected $casts = [
        'status' => 'integer',
        'id' => 'integer',
    ];
    use HasFactory;

    protected $guarded = ['id'];

}
