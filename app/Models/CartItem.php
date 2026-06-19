<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'vendor_id',
        'quantity',
        'mrp',
        'selling_price',
        'tax_rate',
        'estimated_shipping',
        'shipping_mode',
        'price'  // Keeping for backward compatibility
    ];

    protected $casts = [
        'mrp' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'estimated_shipping' => 'decimal:2',
        'quantity' => 'integer'
    ];

    public function cart() 
    { 
        return $this->belongsTo(Cart::class); 
    }
    
    public function product() 
    { 
        return $this->belongsTo(Product::class); 
    }
    
    public function variant() 
    { 
        return $this->belongsTo(ProductVariant::class, 'variant_id'); 
    }
    
    public function vendor() 
    { 
        return $this->belongsTo(Vendor::class, 'vendor_id'); 
    }

    /**
     * Get the item subtotal (selling_price * quantity)
     */
    public function getSubtotalAttribute()
    {
        return $this->selling_price * $this->quantity;
    }

    /**
     * Get the item tax amount
     */
    public function getTaxAmountAttribute()
    {
        return ($this->selling_price * $this->quantity * $this->tax_rate) / 100;
    }

    /**
     * Get the item total with tax
     */
    public function getTotalAttribute()
    {
        return $this->getSubtotalAttribute() + $this->getTaxAmountAttribute();
    }
}