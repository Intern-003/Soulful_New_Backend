<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorStoreSection extends Model
{
    protected $fillable = [
        'vendor_id',
        'title',
        'type',
        'sort_order',
        'status'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}