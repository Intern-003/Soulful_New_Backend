<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = ['order_id','vendor_id','carrier','tracking_number','status','shipped_at','delivered_at'];
    protected $casts = ['shipped_at' => 'datetime','delivered_at' => 'datetime'];

    public function order() { return $this->belongsTo(Order::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
}