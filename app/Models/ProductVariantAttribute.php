<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariantAttribute extends Model
{
    use HasFactory;

    protected $table = 'product_variant_attributes';

    protected $fillable = [
        'variant_id',
        'attribute_id',
        'attribute_value_id'
    ];

    public $timestamps = false;
}