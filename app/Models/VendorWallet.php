<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VendorWallet extends Model
{
    use HasFactory;

    protected $fillable = ['vendor_id','balance'];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function transactions() { return $this->hasMany(VendorTransaction::class); }
}