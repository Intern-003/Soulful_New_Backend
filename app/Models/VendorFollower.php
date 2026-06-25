<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorFollower extends Model
{
    protected $fillable = [
        'vendor_id',
        'user_id'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}