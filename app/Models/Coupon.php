<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id','code','type','value','min_order_amount','max_discount',
        'usage_limit','used_count','start_date','expiry_date','status'
    ];

    protected $casts = [
        'status' => 'boolean',
        'start_date' => 'datetime',
        'expiry_date' => 'datetime'
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
}