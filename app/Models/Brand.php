<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = ['name','slug','logo','status'];
    protected $casts = ['status' => 'boolean'];

    public function products() { return $this->hasMany(Product::class); }
    public function categories()
{
    return $this->belongsToMany(Category::class, 'brand_category');
}
// App\Models\Brand.php

public function subcategories()
{
    return $this->belongsToMany(
        Category::class,
        'brand_subcategory',
        'brand_id',
        'subcategory_id'
    )->whereNotNull('parent_id'); // 🔥 ensures only subcategories
}
}
