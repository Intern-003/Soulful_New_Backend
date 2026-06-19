<?php
// app/Services/OrderCalculationService.php
namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VendorWallet;
use App\Models\AdminWallet;
use App\Models\VendorTransaction;
use App\Models\CouponUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCalculationService
{
    protected $taxService;
    protected $commissionService;
    
    public function __construct(TaxService $taxService, CommissionService $commissionService)
    {
        $this->taxService = $taxService;
        $this->commissionService = $commissionService;
    }
    
    /**
     * Calculate complete order from cart
     */
    public function calculateOrder(Cart $cart, Coupon $coupon = null, $shippingAddress = null)
    {
        // Group cart items by vendor
        $itemsByVendor = $cart->items->groupBy('vendor_id');
        
        $orderData = [
            'items' => [],
            'vendor_totals' => [],
            'totals' => [
                'subtotal' => 0,
                'coupon_discount' => 0,
                'platform_coupon_discount' => 0,
                'vendor_coupon_discount' => 0,
                'tax_total' => 0,
                'shipping_total' => 0,
                'grand_total' => 0
            ]
        ];
        
        // Calculate per vendor with coupon split
        foreach ($itemsByVendor as $vendorId => $items) {
            $vendor = Vendor::find($vendorId);
            $vendorCalculation = $this->calculateVendorTotal($items, $vendor, $coupon);
            
            $orderData['vendor_totals'][$vendorId] = $vendorCalculation;
            $orderData['items'] = array_merge($orderData['items'], $vendorCalculation['items']);
            
            // Accumulate totals
            $orderData['totals']['subtotal'] += $vendorCalculation['subtotal'];
            $orderData['totals']['coupon_discount'] += $vendorCalculation['coupon_discount'];
            $orderData['totals']['platform_coupon_discount'] += $vendorCalculation['platform_coupon_share'];
            $orderData['totals']['vendor_coupon_discount'] += $vendorCalculation['vendor_coupon_share'];
            $orderData['totals']['tax_total'] += $vendorCalculation['tax_total'];
            $orderData['totals']['shipping_total'] += $vendorCalculation['shipping_total'];
            $orderData['totals']['grand_total'] += $vendorCalculation['grand_total'];
        }
        
        return $orderData;
    }
    
    /**
     * Calculate per vendor totals with coupon split
     */
    protected function calculateVendorTotal($items, $vendor, $coupon = null)
    {
        $subtotal = 0;
        $vendorItems = [];
        $vendorCouponDiscount = 0;
        $platformCouponDiscount = 0;
        
        // Calculate item-level details
        foreach ($items as $cartItem) {
            $itemTotal = $cartItem->selling_price * $cartItem->quantity;
            $subtotal += $itemTotal;
            
            $vendorItems[] = [
                'cart_item' => $cartItem,
                'base_total' => $itemTotal
            ];
        }
        
        // Apply coupon if exists and applicable to this vendor
        $couponDiscount = 0;
        $couponSplit = ['vendor_share' => 0, 'admin_share' => 0];
        
        if ($coupon && $this->isCouponApplicableToVendor($coupon, $vendor)) {
            $couponDiscount = $this->calculateCouponDiscount($subtotal, $coupon);
            $couponSplit = $this->splitCouponDiscount($couponDiscount, $coupon);
            $vendorCouponDiscount = $couponSplit['vendor_share'];
            $platformCouponDiscount = $couponSplit['admin_share'];
        }
        
        // Calculate tax on price after product discount but before coupon
        $taxableAmount = $subtotal - $couponDiscount;
        $taxCalculation = $this->taxService->calculateVendorTax($taxableAmount, $vendor);
        
        // Calculate shipping
        $shippingCalculation = $this->calculateShipping($items, $vendor);
        
        // Calculate commission on selling price (before coupon)
        $commissionAmount = $this->commissionService->calculateVendorCommission($subtotal, $vendor);
        
        $grandTotal = $subtotal 
            - $couponDiscount 
            + $taxCalculation['total_tax'] 
            + $shippingCalculation['total_charge'];
        
        // Calculate vendor payout
        $vendorPayout = $subtotal 
            - $vendorCouponDiscount 
            - $commissionAmount 
            + ($shippingCalculation['mode'] === 'vendor' ? $shippingCalculation['total_charge'] : 0);
        
        // Build detailed items with snapshots
        $detailedItems = [];
        foreach ($vendorItems as $item) {
            $cartItem = $item['cart_item'];
            $itemSubtotal = $cartItem->selling_price * $cartItem->quantity;
            
            // Pro-rate coupon discount across items
            $itemCouponDiscount = ($itemSubtotal / $subtotal) * $couponDiscount;
            $itemVendorCouponShare = ($itemSubtotal / $subtotal) * $vendorCouponDiscount;
            $itemAdminCouponShare = ($itemSubtotal / $subtotal) * $platformCouponDiscount;
            
            // Pro-rate tax
            $itemTax = ($itemSubtotal / $subtotal) * $taxCalculation['total_tax'];
            
            // Pro-rate shipping
            $itemShipping = ($itemSubtotal / $subtotal) * $shippingCalculation['total_charge'];
            
            // Calculate item-level commission
            $itemCommission = $this->commissionService->calculateItemCommission($cartItem->selling_price, $cartItem->quantity, $vendor);
            
            $detailedItems[] = [
                'product_id' => $cartItem->product_id,
                'variant_id' => $cartItem->variant_id,
                'vendor_id' => $vendor->id,
                'quantity' => $cartItem->quantity,
                'mrp' => $cartItem->mrp,
                'selling_price' => $cartItem->selling_price,
                'coupon_discount' => $itemCouponDiscount,
                'coupon_funded_by' => $coupon ? $coupon->funded_by : null,
                'vendor_coupon_share' => $itemVendorCouponShare,
                'admin_coupon_share' => $itemAdminCouponShare,
                'tax_rate' => $cartItem->tax_rate,
                'tax_amount' => $itemTax,
                'shipping_mode' => $shippingCalculation['mode'],
                'shipping_charge' => $itemShipping,
                'commission_type' => $vendor->commission_type,
                'commission_rate' => $vendor->commission_rate,
                'commission_amount' => $itemCommission,
                'final_price' => $itemSubtotal - $itemCouponDiscount + $itemTax + $itemShipping,
                'vendor_payout' => $itemSubtotal - $itemVendorCouponShare - $itemCommission + ($shippingCalculation['mode'] === 'vendor' ? $itemShipping : 0)
            ];
        }
        
        return [
            'vendor_id' => $vendor->id,
            'subtotal' => $subtotal,
            'coupon_discount' => $couponDiscount,
            'vendor_coupon_share' => $vendorCouponDiscount,
            'platform_coupon_share' => $platformCouponDiscount,
            'tax_total' => $taxCalculation['total_tax'],
            'shipping_total' => $shippingCalculation['total_charge'],
            'shipping_mode' => $shippingCalculation['mode'],
            'commission_total' => $commissionAmount,
            'vendor_payout' => $vendorPayout,
            'grand_total' => $grandTotal,
            'items' => $detailedItems,
            'tax_breakdown' => $taxCalculation['breakdown']
        ];
    }
    
    /**
     * Calculate coupon discount with max limits
     */
    protected function calculateCouponDiscount($subtotal, Coupon $coupon)
    {
        if ($subtotal < $coupon->minimum_order_amount) {
            return 0;
        }
        
        $discount = 0;
        
        if ($coupon->discount_type === 'percentage') {
            $discount = ($coupon->discount_value / 100) * $subtotal;
        } else {
            $discount = $coupon->discount_value;
        }
        
        // Apply max discount limit
        if ($coupon->maximum_discount_amount && $discount > $coupon->maximum_discount_amount) {
            $discount = $coupon->maximum_discount_amount;
        }
        
        return min($discount, $subtotal);
    }
    
    /**
     * Split coupon discount between admin and vendor
     */
    protected function splitCouponDiscount($discountAmount, Coupon $coupon)
    {
        switch ($coupon->funded_by) {
            case 'admin':
                return [
                    'vendor_share' => 0,
                    'admin_share' => $discountAmount
                ];
            case 'vendor':
                return [
                    'vendor_share' => $discountAmount,
                    'admin_share' => 0
                ];
            case 'shared':
                $vendorShare = ($coupon->vendor_share_percentage / 100) * $discountAmount;
                return [
                    'vendor_share' => $vendorShare,
                    'admin_share' => $discountAmount - $vendorShare
                ];
            default:
                return [
                    'vendor_share' => 0,
                    'admin_share' => 0
                ];
        }
    }
    
    /**
     * Calculate shipping based on vendor settings
     */
    protected function calculateShipping($items, Vendor $vendor)
    {
        $settings = $vendor->settings;
        
        if (!$settings) {
            return [
                'mode' => 'vendor',
                'total_charge' => 0
            ];
        }
        
        $totalWeight = 0;
        $totalPrice = 0;
        
        foreach ($items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $totalWeight += ($product->weight ?? 0) * $item->quantity;
                $totalPrice += $item->selling_price * $item->quantity;
            }
        }
        
        $shippingCharge = $settings->calculateShipping([
            'weight' => $totalWeight,
            'price' => $totalPrice,
            'items' => $items
        ], null);
        
        return [
            'mode' => $settings->shipping_mode ?? 'vendor',
            'total_charge' => $shippingCharge
        ];
    }
    
    /**
     * Check if coupon applies to vendor
     */
    protected function isCouponApplicableToVendor(Coupon $coupon, Vendor $vendor)
    {
        if ($coupon->applies_to === 'all') {
            return true;
        }
        
        if ($coupon->applies_to === 'vendor') {
            return $coupon->vendor_id === $vendor->id;
        }
        
        // For category/product specific, check items
        return true; // Will be filtered at item level
    }
    
    /**
     * Create order from calculated data
     */
    public function createOrder(Cart $cart, $calculatedData, $userId, $shippingAddress, $paymentMethod = null)
    {
        DB::beginTransaction();
        
        try {
            // Create order
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $userId,
                'coupon_id' => $calculatedData['coupon_id'] ?? null,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $paymentMethod,
                'subtotal' => $calculatedData['totals']['subtotal'],
                'coupon_discount' => $calculatedData['totals']['coupon_discount'],
                'platform_coupon_discount' => $calculatedData['totals']['platform_coupon_discount'],
                'vendor_coupon_discount' => $calculatedData['totals']['vendor_coupon_discount'],
                'tax_total' => $calculatedData['totals']['tax_total'],
                'shipping_total' => $calculatedData['totals']['shipping_total'],
                'grand_total' => $calculatedData['totals']['grand_total'],
                'shipping_address' => $shippingAddress,
                'settlement_status' => 'pending'
            ]);
            
            // Create order items
            foreach ($calculatedData['items'] as $itemData) {
                $product = Product::withTrashed()->find($itemData['product_id']);
                
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $itemData['product_id'],
                    'variant_id' => $itemData['variant_id'],
                    'vendor_id' => $itemData['vendor_id'],
                    'product_name' => $product->name,
                    'product_sku' => $itemData['variant_id'] ? 
                        ProductVariant::find($itemData['variant_id'])->sku : 
                        $product->sku,
                    'quantity' => $itemData['quantity'],
                    'mrp' => $itemData['mrp'],
                    'selling_price' => $itemData['selling_price'],
                    'coupon_discount' => $itemData['coupon_discount'],
                    'coupon_funded_by' => $itemData['coupon_funded_by'],
                    'vendor_coupon_share' => $itemData['vendor_coupon_share'],
                    'admin_coupon_share' => $itemData['admin_coupon_share'],
                    'tax_rate' => $itemData['tax_rate'],
                    'tax_amount' => $itemData['tax_amount'],
                    'shipping_mode' => $itemData['shipping_mode'],
                    'shipping_charge' => $itemData['shipping_charge'],
                    'commission_type' => $itemData['commission_type'],
                    'commission_rate' => $itemData['commission_rate'],
                    'commission_amount' => $itemData['commission_amount'],
                    'final_price' => $itemData['final_price'],
                    'vendor_payout' => $itemData['vendor_payout'],
                    'settlement_status' => 'pending'
                ]);
                
                // Update vendor wallet pending balance
                $this->updateVendorPendingBalance($itemData['vendor_id'], $itemData['vendor_payout'], $orderItem);
                
                // Update product sold count
                $product->increment('sold_count', $itemData['quantity']);
                $product->decrement('stock', $itemData['quantity']);
            }
            
            // Record coupon usage
            if ($calculatedData['coupon_id'] ?? null) {
                CouponUsage::create([
                    'coupon_id' => $calculatedData['coupon_id'],
                    'user_id' => $userId,
                    'order_id' => $order->id,
                    'discount_amount' => $calculatedData['totals']['coupon_discount'],
                    'breakdown' => [
                        'platform_share' => $calculatedData['totals']['platform_coupon_discount'],
                        'vendor_share' => $calculatedData['totals']['vendor_coupon_discount']
                    ]
                ]);
                
                // Increment coupon usage count
                Coupon::where('id', $calculatedData['coupon_id'])->increment('used_count');
            }
            
            // Clear cart
            $cart->items()->delete();
            
            DB::commit();
            
            return $order;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update vendor pending balance
     */
    protected function updateVendorPendingBalance($vendorId, $amount, $orderItem)
    {
        $wallet = VendorWallet::firstOrCreate(
            ['vendor_id' => $vendorId],
            [
                'balance' => 0,
                'pending_balance' => 0,
                'available_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'total_commission_paid' => 0
            ]
        );
        
        $wallet->increment('pending_balance', $amount);
        $wallet->increment('total_earned', $amount);
        
        // Create transaction record
        VendorTransaction::create([
            'vendor_wallet_id' => $wallet->id,
            'vendor_id' => $vendorId,
            'order_item_id' => $orderItem->id,
            'amount' => $orderItem->selling_price * $orderItem->quantity,
            'coupon_amount' => $orderItem->vendor_coupon_share,
            'tax_amount' => $orderItem->tax_amount,
            'shipping_amount' => $orderItem->shipping_mode === 'vendor' ? $orderItem->shipping_charge : 0,
            'commission' => $orderItem->commission_amount,
            'commission_rate' => $orderItem->commission_rate,
            'net_amount' => $amount,
            'type' => 'credit',
            'description' => "Earnings from Order #{$orderItem->order->order_number}",
            'status' => 'pending',
            'reference_id' => $orderItem->order_id
        ]);
    }
    
    /**
     * Generate unique order number
     */
    protected function generateOrderNumber()
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        
        $orderNumber = $prefix . $date . $random;
        
        while (Order::where('order_number', $orderNumber)->exists()) {
            $random = strtoupper(substr(uniqid(), -6));
            $orderNumber = $prefix . $date . $random;
        }
        
        return $orderNumber;
    }
}