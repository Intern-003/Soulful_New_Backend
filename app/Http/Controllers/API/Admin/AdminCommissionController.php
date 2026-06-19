<?php
// app/Http/Controllers/API/Admin/AdminCommissionController.php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AdminCommissionController extends Controller
{
    /**
     * Get all vendors with commission settings
     * GET /admin/commissions/vendors
     */
    public function vendors(Request $request)
    {
        $query = Vendor::with(['user:id,name,email'])
            ->select('id', 'store_name', 'commission_rate', 'status');
        if ($request->has('search')) {
            $query->where('store_name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $vendors = $query->paginate(15);

        // Add statistics to each vendor
        $vendors->getCollection()->transform(function ($vendor) {
            $vendor->total_sales = OrderItem::where('vendor_id', $vendor->id)
                ->sum('vendor_payout');
            $vendor->total_commission = OrderItem::where('vendor_id', $vendor->id)
                ->sum('commission_amount');
            $vendor->pending_commission = OrderItem::where('vendor_id', $vendor->id)
                ->where('settlement_status', 'pending')
                ->sum('commission_amount');
            return $vendor;
        });

        return response()->json([
            'success' => true,
            'data' => $vendors
        ]);
    }

    /**
     * Get single vendor commission details
     * GET /admin/commissions/vendors/{id}
     */
    public function showVendor($id)
    {
        $vendor = Vendor::with(['user:id,name,email'])
            ->findOrFail($id);

        // Get commission statistics
        $stats = [
            'total_sales' => OrderItem::where('vendor_id', $vendor->id)->sum('vendor_payout'),
            'total_commission' => OrderItem::where('vendor_id', $vendor->id)->sum('commission_amount'),
            'pending_commission' => OrderItem::where('vendor_id', $vendor->id)
                ->where('settlement_status', 'pending')
                ->sum('commission_amount'),
            'settled_commission' => OrderItem::where('vendor_id', $vendor->id)
                ->where('settlement_status', 'settled')
                ->sum('commission_amount'),
            'product_count' => Product::where('vendor_id', $vendor->id)->count(),
            'product_commission_overrides' => Product::where('vendor_id', $vendor->id)
                ->whereNotNull('vendor_commission')
                ->count()
        ];

        // Get products with custom commission
        $customCommissionProducts = Product::where('vendor_id', $vendor->id)
            ->whereNotNull('vendor_commission')
            ->get(['id', 'name', 'vendor_commission', 'commission_type']);

        return response()->json([
            'success' => true,
            'data' => [
                'vendor' => $vendor,
                'statistics' => $stats,
                'custom_commission_products' => $customCommissionProducts
            ]
        ]);
    }

    /**
     * Update vendor commission
     * POST /admin/commissions/vendors/{id}
     */
    public function updateVendorCommission(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'commission_rate' => 'required|numeric|min:0|max:100',
            'commission_type' => 'required|in:percentage,fixed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $vendor->update([
            'commission_rate' => $request->commission_rate,
            'commission_type' => $request->commission_type
        ]);

        // Log commission change
        \App\Models\CommissionLog::create([
            'vendor_id' => $vendor->id,
            'old_rate' => $vendor->getOriginal('commission_rate'),
            'new_rate' => $request->commission_rate,
            'old_type' => $vendor->getOriginal('commission_type'),
            'new_type' => $request->commission_type,
            'changed_by' => auth()->id(),
            'reason' => $request->reason
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor commission updated successfully',
            'data' => $vendor
        ]);
    }

    /**
     * Update product commission override
     * POST /admin/commissions/products/{id}
     */
    public function updateProductCommission(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'vendor_commission' => 'nullable|numeric|min:0|max:100',
            'commission_type' => 'required_with:vendor_commission|in:percentage,fixed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update([
            'vendor_commission' => $request->vendor_commission,
            'commission_type' => $request->commission_type
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product commission override updated',
            'data' => $product
        ]);
    }

    /**
     * Get commission report
     * GET /admin/commissions/report
     */
    public function report(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'vendor_id' => 'nullable|exists:vendors,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = OrderItem::with(['vendor', 'order'])
            ->whereBetween('created_at', [$request->from_date, $request->to_date]);

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        $commissionData = $query->get();

        // Summary
        $summary = [
            'total_sales' => $commissionData->sum('vendor_payout'),
            'total_commission' => $commissionData->sum('commission_amount'),
            'avg_commission_rate' => $commissionData->avg('commission_rate'),
            'total_items' => $commissionData->sum('quantity'),
            'total_orders' => $commissionData->unique('order_id')->count()
        ];

        // Vendor breakdown
        $vendorBreakdown = $commissionData->groupBy('vendor_id')->map(function ($items, $vendorId) {
            $vendor = $items->first()->vendor;
            return [
                'vendor_id' => $vendorId,
                'vendor_name' => $vendor ? $vendor->store_name : 'Unknown',
                'sales' => $items->sum('vendor_payout'),
                'commission' => $items->sum('commission_amount'),
                'effective_rate' => $items->sum('sales') > 0 ?
                    ($items->sum('commission_amount') / $items->sum('vendor_payout')) * 100 : 0,
                'items_count' => $items->sum('quantity')
            ];
        })->values();

        // Daily breakdown
        $dailyBreakdown = $commissionData->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d');
        })->map(function ($items, $date) {
            return [
                'date' => $date,
                'sales' => $items->sum('vendor_payout'),
                'commission' => $items->sum('commission_amount')
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $request->from_date,
                    'to' => $request->to_date
                ],
                'summary' => $summary,
                'vendor_breakdown' => $vendorBreakdown,
                'daily_breakdown' => $dailyBreakdown
            ]
        ]);
    }

    /**
     * Process commission settlement for vendor
     * POST /admin/commissions/settle
     */
    public function settleCommission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|exists:vendors,id',
            'order_item_ids' => 'nullable|array',
            'order_item_ids.*' => 'exists:order_items,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // ✅ Fixed: Remove 'status' condition - only use settlement_status
            $query = OrderItem::where('vendor_id', $request->vendor_id)
                ->where('settlement_status', 'pending');

            if ($request->has('order_item_ids')) {
                $query->whereIn('id', $request->order_item_ids);
            }

            $items = $query->get();

            if ($items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending settlements found for this vendor'
                ], 404);
            }

            $totalAmount = $items->sum('vendor_payout');
            $totalCommission = $items->sum('commission_amount');

            // Create settlement record
            $settlement = Settlement::create([
                'settlement_number' => $this->generateSettlementNumber(),
                'vendor_id' => $request->vendor_id,
                'period_start' => $items->min('created_at'),
                'period_end' => $items->max('created_at'),
                'total_sales' => $items->sum(function ($item) {
                    return $item->selling_price * $item->quantity;
                }),
                'total_commission' => $totalCommission,
                'total_tax' => $items->sum('tax_amount'),
                'total_shipping' => $items->sum('shipping_charge'),
                'settlement_amount' => $totalAmount,
                'status' => 'processing',
                'breakdown' => json_encode([
                    'items' => $items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'order_id' => $item->order_id,
                            'product_name' => $item->product_name,
                            'amount' => $item->vendor_payout,
                            'commission' => $item->commission_amount
                        ];
                    })
                ]),
                'processed_by' => auth()->id()
            ]);

            // Update order items
            foreach ($items as $item) {
                $item->update([
                    'settlement_status' => 'settled',
                    'settled_at' => now()
                ]);
            }

            // Update vendor wallet (move from pending to available)
            $wallet = \App\Models\VendorWallet::firstOrCreate(
                ['vendor_id' => $request->vendor_id],
                [
                    'balance' => 0,
                    'pending_balance' => 0,
                    'available_balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0
                ]
            );

            $wallet->decrement('pending_balance', $totalAmount);
            $wallet->increment('available_balance', $totalAmount);

            // Create transaction record
            \App\Models\VendorTransaction::create([
                'vendor_wallet_id' => $wallet->id,
                'vendor_id' => $request->vendor_id,
                'amount' => $totalAmount,
                'net_amount' => $totalAmount,
                'type' => 'credit',
                'source' => 'settlement',
                'reference_id' => $settlement->id,
                'description' => "Settlement #{$settlement->settlement_number}",
                'status' => 'completed'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commission settled successfully',
                'data' => [
                    'settlement_id' => $settlement->id,
                    'settlement_number' => $settlement->settlement_number,
                    'items_settled' => $items->count(),
                    'total_amount' => $totalAmount,
                    'total_commission' => $totalCommission
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process settlement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get settlement history
     * GET /admin/commissions/settlements
     */
    public function settlements(Request $request)
    {
        $query = Settlement::with(['vendor', 'processedBy']);

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $settlements = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $settlements
        ]);
    }

    /**
     * Get single settlement details
     * GET /admin/commissions/settlements/{id}
     */
    public function showSettlement($id)
    {
        $settlement = Settlement::with(['vendor', 'processedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $settlement
        ]);
    }

    /**
     * Update settlement status
     * PUT /admin/commissions/settlements/{id}
     */
    public function updateSettlement(Request $request, $id)
    {
        $settlement = Settlement::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:processing,completed,failed',
            'transaction_id' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $settlement->update([
            'status' => $request->status,
            'transaction_id' => $request->transaction_id,
            'notes' => $request->notes,
            'processed_at' => $request->status === 'completed' ? now() : $settlement->processed_at
        ]);

        // If settlement is completed, update wallet balance
        if ($request->status === 'completed') {
            $wallet = \App\Models\VendorWallet::where('vendor_id', $settlement->vendor_id)->first();
            if ($wallet) {
                $wallet->increment('balance', $settlement->settlement_amount);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Settlement status updated',
            'data' => $settlement
        ]);
    }

    /**
     * Get commission dashboard stats
     * GET /admin/commissions/dashboard
     */
    /**
     * Get commission dashboard stats
     * GET /admin/commissions/dashboard
     */
    public function dashboard(Request $request)
    {
        // Current month stats
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();

        $currentMonthSales = OrderItem::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('vendor_payout');
        $currentMonthCommission = OrderItem::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('commission_amount');

        // ✅ Fixed: Remove 'status' condition - use shipment status or remove
        // Pending settlements (items eligible for settlement)
        $pendingSettlements = OrderItem::where('settlement_status', 'pending')
            ->sum('commission_amount');

        // ✅ Alternative: If you want only delivered items, use shipment relationship
        // $pendingSettlements = OrderItem::where('settlement_status', 'pending')
        //     ->whereHas('shipment', function($q) {
        //         $q->where('status', 'delivered');
        //     })
        //     ->sum('commission_amount');

        // Top vendors by commission
        $topVendors = OrderItem::select('vendor_id')
            ->selectRaw('SUM(commission_amount) as total_commission')
            ->with('vendor:id,store_name')
            ->groupBy('vendor_id')
            ->orderBy('total_commission', 'desc')
            ->limit(10)
            ->get();

        // Commission trend (last 12 months)
        $trend = OrderItem::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(commission_amount) as total')
            ->where('created_at', '>=', Carbon::now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'current_month' => [
                    'sales' => round($currentMonthSales, 2),
                    'commission' => round($currentMonthCommission, 2),
                    'effective_rate' => $currentMonthSales > 0 ?
                        round(($currentMonthCommission / $currentMonthSales) * 100, 2) : 0
                ],
                'pending_settlements' => round($pendingSettlements, 2),
                'top_vendors' => $topVendors,
                'trend' => $trend,
                'total_vendors' => Vendor::count(),
                'total_commission_lifetime' => OrderItem::sum('commission_amount')
            ]
        ]);
    }

    /**
     * Generate unique settlement number
     */
    private function generateSettlementNumber()
    {
        $prefix = 'STL';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        $settlementNumber = $prefix . $date . $random;

        while (Settlement::where('settlement_number', $settlementNumber)->exists()) {
            $random = strtoupper(substr(uniqid(), -6));
            $settlementNumber = $prefix . $date . $random;
        }

        return $settlementNumber;
    }
}