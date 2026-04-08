<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['order_id','product_id','variant_id','vendor_id','creator_id','price','quantity','total','status'];

    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class,'variant_id'); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function vendorTransactions() { return $this->hasMany(VendorTransaction::class); }
}