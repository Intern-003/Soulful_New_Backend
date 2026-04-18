<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'user_id',
        'category_id',
        'brand_id',
        'name',
        'slug',
        'short_description',
        'description',
        'sku',
        'price',
        'discount_price',
        'cost_price',
        'stock',
        'weight',
        'length',
        'width',
        'height',
        'status',
        'is_featured',
        'approval_status',
        'commission',
        'rejection_reason',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_featured' => 'boolean',
        'approved_at' => 'datetime'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'product_tags');
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function specifications()
    {
        return $this->hasMany(ProductSpecification::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_primary', 1);
    }

    public function banners()
    {
        return $this->belongsToMany(Banner::class, 'banner_products')
            ->withPivot('position');
    }
}