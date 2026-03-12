<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id','category_id','brand_id','name','slug','short_description','description','sku',
        'price','discount_price','cost_price','stock','weight','length','width','height',
        'status','is_featured','is_approved','approved_by','approved_at'
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_featured' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime'
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function images() { return $this->hasMany(ProductImage::class); }
    public function variants() { return $this->hasMany(ProductVariant::class); }
    public function tags() { return $this->belongsToMany(Tag::class,'product_tags'); }
    public function reviews() { return $this->hasMany(Review::class); }
}