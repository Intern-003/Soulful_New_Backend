<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'price',
        'discount_price',
        'cost_price',        // ✅ ADD THIS
        'tax_rate',          // ✅ ADD THIS
        'shipping_charge',   // ✅ ADD THIS
        'stock',
        'weight',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'shipping_charge' => 'decimal:2',
        'stock' => 'integer',
        'weight' => 'decimal:2'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_attributes', 'variant_id', 'attribute_value_id');
    }

    public function attributes()
    {
        return $this->belongsToMany(
            Attribute::class,
            'product_variant_attributes',
            'variant_id',
            'attribute_id'
        )->withPivot('attribute_value_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }

    /**
     * Get the final selling price (after discount)
     */
    public function getFinalPriceAttribute()
    {
        return $this->discount_price ?? $this->price;
    }

    /**
     * Get tax amount for this variant
     */
    public function getTaxAmount($quantity = 1)
    {
        return ($this->final_price * $quantity * ($this->tax_rate ?? 18)) / 100;
    }

    /**
     * Get shipping charge for this variant
     */
    public function getShippingChargeAttribute()
    {
        return $this->shipping_charge ?? $this->product->shipping_charge ?? 0;
    }
}