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
        'stock',
        'weight',
       
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    //public function attributes() { return $this->belongsToMany(Attribute::class,'product_variant_attributes'); }
    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_attributes', 'variant_id', 'attribute_value_id');
    }

    public function attributes()
    {
        return $this->belongsToMany(
            Attribute::class,
            'product_variant_attributes',
            'variant_id',     // pivot FK for ProductVariant
            'attribute_id'    // pivot FK for Attribute
        )->withPivot('attribute_value_id');
    }

    public function images()
{
    return $this->hasMany(ProductImage::class, 'variant_id');
}
}