<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $table = 'otps';
    
    protected $fillable = [
        'identifier',
        'type',
        'otp',
        'name',
        'email',      // Added missing field
        'phone',      // Added missing field
        'password',
        'attempts',
        'blocked_until',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'blocked_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'otp',
        'password',
    ];
}