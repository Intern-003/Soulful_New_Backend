<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'identifier',
        'type',
        'otp',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}