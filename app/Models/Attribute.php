<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = ['name','slug','status'];
    protected $casts = ['status' => 'boolean'];

    public function values() { return $this->hasMany(AttributeValue::class); }
    public function variants() { return $this->belongsToMany(ProductVariant::class,'product_variant_attributes'); }
}