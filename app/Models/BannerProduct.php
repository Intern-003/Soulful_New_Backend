<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BannerProduct extends Model
{
    use HasFactory;

    protected $table = 'banner_products';

    protected $fillable = [
        'banner_id',
        'product_id',
        'position',
    ];

    public function banner()
    {
        return $this->belongsTo(Banner::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}