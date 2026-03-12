<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['order_id','payment_gateway','transaction_id','amount','status','paid_at'];
    protected $casts = ['paid_at' => 'datetime'];

    public function order() { return $this->belongsTo(Order::class); }
}