<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'link',
        'position',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];
    public function products()
{
    return $this->belongsToMany(Product::class, 'banner_products')
        ->withPivot('position')
        ->orderBy('banner_products.position');
}
}