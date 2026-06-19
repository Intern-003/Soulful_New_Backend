<?php
// app/Http/Controllers/API/Vendor/VendorPayoutController.php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderItem;
use App\Models\Settlement;
use App\Models\VendorWallet;
use App\Models\WithdrawRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class VendorPayoutController extends Controller
{
    /**
     * Get payout dashboard summary
     * GET /vendor/payout/dashboard
     */
    public function dashboard(Request $request)
    {
         $user = $request->user();
    $vendorId = optional($user->vendor)->id;
    
    if (!$vendorId) {
        return response()->json([
            'success' => false,
            'message' => 'Vendor not found'
        ], 404);
    }
    
    $wallet = VendorWallet::where('vendor_id', $vendorId)->first();
    
    // ✅ Remove 'status' condition
    $pendingSettlements = OrderItem::where('vendor_id', $vendorId)
        ->where('settlement_status', 'pending')
        // ❌ Remove: ->where('status', 'delivered')
        ->where(function($q) {
            $q->whereNull('eligible_for_settlement_at')
              ->orWhere('eligible_for_settlement_at', '<=', now());
        })
        ->sum('vendor_payout');

        
        // Get this month's earnings
        $thisMonthEarnings = OrderItem::where('vendor_id', $vendorId)
            ->where('settlement_status', 'settled')
            ->whereMonth('settled_at', Carbon::now()->month)
            ->sum('vendor_payout');
        
        // Get last month's earnings
        $lastMonthEarnings = OrderItem::where('vendor_id', $vendorId)
            ->where('settlement_status', 'settled')
            ->whereMonth('settled_at', Carbon::now()->subMonth()->month)
            ->sum('vendor_payout');
        
        // Calculate growth
        $growth = $lastMonthEarnings > 0 ? 
            (($thisMonthEarnings - $lastMonthEarnings) / $lastMonthEarnings) * 100 : 0;
        
        // Get recent payouts
        $recentPayouts = Settlement::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->latest()
            ->limit(5)
            ->get();
        
        // Get withdrawal requests summary
        $withdrawals = WithdrawRequest::where('vendor_id', $vendorId)
            ->selectRaw('status, SUM(amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        
        return response()->json([
            'success' => true,
            'data' => [
                'wallet_balance' => [
                    'available' => round($wallet->available_balance ?? 0, 2),
                    'pending' => round($wallet->pending_balance ?? 0, 2),
                    'total_earned' => round($wallet->total_earned ?? 0, 2)
                ],
                'pending_settlements' => round($pendingSettlements, 2),
                'monthly_earnings' => [
                    'current_month' => round($thisMonthEarnings, 2),
                    'previous_month' => round($lastMonthEarnings, 2),
                    'growth_percentage' => round($growth, 2)
                ],
                'recent_payouts' => $recentPayouts,
                'withdrawal_summary' => [
                    'pending' => $withdrawals['pending'] ?? 0,
                    'approved' => $withdrawals['approved'] ?? 0,
                    'rejected' => $withdrawals['rejected'] ?? 0,
                    'completed' => $withdrawals['completed'] ?? 0
                ]
            ]
        ]);
    }

    /**
     * Get payout history
     * GET /vendor/payout/history
     */
    public function history(Request $request)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;
        
        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }
        
        $query = Settlement::where('vendor_id', $vendorId);
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        $payouts = $query->latest()->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $payouts
        ]);
    }

    /**
     * Get payout details for a specific settlement
     * GET /vendor/payout/{id}
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;
        
        $payout = Settlement::where('id', $id)
            ->where('vendor_id', $vendorId)
            ->firstOrFail();
        
        // Parse breakdown
        $breakdown = json_decode($payout->breakdown, true);
        
        return response()->json([
            'success' => true,
            'data' => [
                'settlement' => $payout,
                'breakdown' => $breakdown,
                'items' => isset($breakdown['items']) ? $breakdown['items'] : []
            ]
        ]);
    }

    /**
     * Get earnings report by period
     * GET /vendor/payout/earnings-report
     */
    public function earningsReport(Request $request)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;
        
        $validator = Validator::make($request->all(), [
            'period' => 'required|in:week,month,quarter,year',
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1)
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $year = $request->year;
        $period = $request->period;
        
        $query = OrderItem::where('vendor_id', $vendorId)
            ->whereYear('settled_at', $year)
            ->where('settlement_status', 'settled');
        
        switch ($period) {
            case 'week':
                $groupBy = 'week';
                $format = 'W';
                $data = $query->selectRaw('WEEK(settled_at) as period, SUM(vendor_payout) as total')
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
                break;
            case 'month':
                $groupBy = 'month';
                $format = 'M';
                $data = $query->selectRaw('MONTH(settled_at) as period, SUM(vendor_payout) as total')
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
                break;
            case 'quarter':
                $groupBy = 'quarter';
                $format = 'Q';
                $data = $query->selectRaw('QUARTER(settled_at) as period, SUM(vendor_payout) as total')
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
                break;
            default:
                $groupBy = 'month';
                $format = 'M';
                $data = $query->selectRaw('MONTH(settled_at) as period, SUM(vendor_payout) as total')
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
        }
        
        // Calculate totals
        $totalEarned = $data->sum('total');
        $average = $data->avg('total');
        
        return response()->json([
            'success' => true,
            'data' => [
                'period_type' => $period,
                'year' => $year,
                'data' => $data,
                'summary' => [
                    'total_earned' => round($totalEarned, 2),
                    'average_per_period' => round($average, 2),
                    'best_period' => $data->sortByDesc('total')->first(),
                    'periods_with_earnings' => $data->count()
                ]
            ]
        ]);
    }

    /**
     * Request early settlement (if allowed)
     * POST /vendor/payout/request-early-settlement
     */
    public function requestEarlySettlement(Request $request)
    {
           $user = $request->user();
    $vendorId = optional($user->vendor)->id;
    
    $validator = Validator::make($request->all(), [
        'order_item_ids' => 'required|array|min:1',
        'order_item_ids.*' => 'exists:order_items,id',
        'reason' => 'nullable|string|max:500'
    ]);
    
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }
    
    // ✅ Remove 'status' condition - use shipment status instead
    $items = OrderItem::whereIn('id', $request->order_item_ids)
        ->where('vendor_id', $vendorId)
        ->where('settlement_status', 'pending')
        ->whereHas('shipment', function($q) {
            $q->where('status', 'delivered');
        })
        ->get();
        
        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No eligible items found for early settlement'
            ], 404);
        }
        
        // Create early settlement request
        $earlySettlement = \App\Models\EarlySettlementRequest::create([
            'vendor_id' => $vendorId,
            'order_item_ids' => json_encode($request->order_item_ids),
            'total_amount' => $items->sum('vendor_payout'),
            'reason' => $request->reason,
            'status' => 'pending',
            'requested_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Early settlement request submitted',
            'data' => [
                'request_id' => $earlySettlement->id,
                'total_amount' => $items->sum('vendor_payout'),
                'items_count' => $items->count(),
                'status' => 'pending_review'
            ]
        ]);
    }

    /**
     * Get tax summary for vendor (GST reports)
     * GET /vendor/payout/tax-summary
     */
    public function taxSummary(Request $request)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;
        
        $validator = Validator::make($request->all(), [
            'financial_year' => 'required|string|regex:/^\d{4}-\d{4}$/'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'=> $validator->errors()
            ], 422);
        }
        
        list($startYear, $endYear) = explode('-', $request->financial_year);
        $startDate = Carbon::create($startYear, 4, 1);
        $endDate = Carbon::create($endYear, 3, 31);
        
        $items = OrderItem::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('settlement_status', 'settled')
            ->get();
        
        $taxSummary = [
            'total_sales' => $items->sum(function($item) {
                return $item->selling_price * $item->quantity;
            }),
            'total_tax_collected' => $items->sum('tax_amount'),
            'total_commission' => $items->sum('commission_amount'),
            'net_payout' => $items->sum('vendor_payout'),
            'tax_breakdown' => [
                'cgst' => $items->sum(function($item) {
                    $breakdown = json_decode($item->tax_breakdown, true);
                    return $breakdown['cgst']['amount'] ?? 0;
                }),
                'sgst' => $items->sum(function($item) {
                    $breakdown = json_decode($item->tax_breakdown, true);
                    return $breakdown['sgst']['amount'] ?? 0;
                }),
                'igst' => $items->sum(function($item) {
                    $breakdown = json_decode($item->tax_breakdown, true);
                    return $breakdown['igst']['amount'] ?? 0;
                })
            ]
        ];
        
        return response()->json([
            'success' => true,
            'data' => [
                'financial_year' => $request->financial_year,
                'summary' => $taxSummary,
                'items_count' => $items->count()
            ]
        ]);
    }

    /**
     * Download payout statement
     * GET /vendor/payout/statement/download
     */
    public function downloadStatement(Request $request)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;
        
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $settlements = Settlement::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$request->from_date, $request->to_date])
            ->where('status', 'completed')
            ->orderBy('created_at', 'asc')
            ->get();
        
        $csvData = [];
        $csvData[] = ['Date', 'Settlement Number', 'Period Start', 'Period End', 'Total Sales', 'Commission', 'Tax', 'Shipping', 'Net Amount', 'Status'];
        
        foreach ($settlements as $settlement) {
            $csvData[] = [
                $settlement->created_at->format('Y-m-d'),
                $settlement->settlement_number,
                $settlement->period_start,
                $settlement->period_end,
                number_format($settlement->total_sales, 2),
                number_format($settlement->total_commission, 2),
                number_format($settlement->total_tax, 2),
                number_format($settlement->total_shipping, 2),
                number_format($settlement->settlement_amount, 2),
                $settlement->status
            ];
        }
        
        // Add totals row
        $csvData[] = [];
        $csvData[] = ['TOTAL', '', '', '', 
            number_format($settlements->sum('total_sales'), 2),
            number_format($settlements->sum('total_commission'), 2),
            number_format($settlements->sum('total_tax'), 2),
            number_format($settlements->sum('total_shipping'), 2),
            number_format($settlements->sum('settlement_amount'), 2),
            ''
        ];
        
        $csv = $this->arrayToCsv($csvData);
        
        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="payout_statement_' . date('Ymd_His') . '.csv"');
    }
    
    /**
     * Convert array to CSV
     */
    private function arrayToCsv($data)
    {
        $output = fopen('php://temp', 'r+');
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        return stream_get_contents($output);
    }
}