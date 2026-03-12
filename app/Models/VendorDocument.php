<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VendorDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id','document_type','document_number','document_file','status','verified_by','verified_at'
    ];

    protected $casts = ['verified_at' => 'datetime'];

    public function vendor() { return $this->belongsTo(Vendor::class); }
}