<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VendorTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['vendor_id','order_item_id','amount','commission','net_amount','status'];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
}