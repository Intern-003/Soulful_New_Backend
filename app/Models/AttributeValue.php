<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = ['attribute_id', 'value', 'slug'];

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
    //public function variants() { return $this->belongsToMany(ProductVariant::class,'product_variant_attributes','attribute_value_id','variant_id'); }
    public function variants()
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_attributes',
            'attribute_value_id',
            'variant_id'
        );
    }
}