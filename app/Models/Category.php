<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id','name','slug','description','image','position','status'];

    protected $casts = ['status' => 'boolean'];

    public function parent() { return $this->belongsTo(Category::class,'parent_id'); }
    public function children() { return $this->hasMany(Category::class,'parent_id'); }
    public function products() { return $this->hasMany(Product::class); }
    public function brands()
{
    return $this->belongsToMany(
        Brand::class,
        'brand_subcategory',
        'subcategory_id',
        'brand_id'
    );
}
}