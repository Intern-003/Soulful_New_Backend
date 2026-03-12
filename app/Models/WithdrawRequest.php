<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WithdrawRequest extends Model
{
    use HasFactory;

    protected $fillable = ['vendor_id','amount','status','requested_at','approved_at'];
    protected $casts = ['requested_at'=>'datetime','approved_at'=>'datetime'];

    public function vendor() { return $this->belongsTo(Vendor::class); }
}