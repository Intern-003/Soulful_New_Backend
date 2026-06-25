<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorStoreBanner extends Model
{
    protected $fillable = [
        'vendor_id',
        'title',
        'image',
        'button_text',
        'button_link',
        'sort_order',
        'status'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}