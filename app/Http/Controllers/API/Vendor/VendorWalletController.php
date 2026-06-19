<?php
// app/Http/Controllers/API/Vendor/VendorWalletController.php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VendorWallet;
use App\Models\VendorTransaction;
use App\Models\WithdrawRequest;
use App\Models\OrderItem;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class VendorWalletController extends Controller
{
    /**
     * Helper to get wallet owner (vendor or individual seller)
     */
    private function getWalletOwner($user)
    {
        if ($user->vendor) {
            return [
                'type' => 'vendor',
                'id' => $user->vendor->id,
                'field' => 'vendor_id'
            ];
        }
        
        return [
            'type' => 'user',
            'id' => $user->id,
            'field' => 'user_id'
        ];
    }

    /**
     * Get wallet details with complete balance breakdown
     * GET /vendor/wallet
     */
    public function wallet(Request $request)
    {
        $user = $request->user();
        $owner = $this->getWalletOwner($user);
        
        $wallet = VendorWallet::where($owner['field'], $owner['id'])->first();
        
        if (!$wallet) {
            $wallet = VendorWallet::create([
                $owner['field'] => $owner['id'],
                'balance' => 0,
                'pending_balance' => 0,
                'available_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'total_commission_paid' => 0
            ]);
        }

        // Calculate detailed balances
        $balances = $this->calculateDetailedBalances($wallet);
        
        // Get recent transactions
        $recentTransactions = VendorTransaction::where('vendor_wallet_id', $wallet->id)
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => round($wallet->balance, 2),
                    'pending_balance' => round($wallet->pending_balance, 2),
                    'available_balance' => round($wallet->available_balance, 2),
                    'total_earned' => round($wallet->total_earned, 2),
                    'total_withdrawn' => round($wallet->total_withdrawn, 2),
                    'total_commission_paid' => round($wallet->total_commission_paid, 2)
                ],
                'balances' => $balances,
                'recent_transactions' => $recentTransactions,
                'withdrawal_info' => [
                    'min_withdrawal_amount' => 100,
                    'max_withdrawal_amount' => $wallet->available_balance,
                    'processing_days' => '2-3 business days',
                    'bank_charges' => 0
                ]
            ]
        ]);
    }

    /**
     * Get wallet transactions with filters
     * GET /vendor/wallet/transactions
     */
    public function transactions(Request $request)
    {
        $user = $request->user();
        $owner = $this->getWalletOwner($user);
        
        $wallet = VendorWallet::where($owner['field'], $owner['id'])->first();
        
        if (!$wallet) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 0]
            ]);
        }
        
        $query = VendorTransaction::where('vendor_wallet_id', $wallet->id)
            ->with(['orderItem.product', 'orderItem.order']);
        
        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('source')) {
            $query->where('source', $request->source);
        }
        
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        $transactions = $query->latest()->paginate(20);
        
        // Calculate summary
        $summary = [
            'total_credit' => $query->where('type', 'credit')->where('status', 'completed')->sum('net_amount'),
            'total_debit' => $query->where('type', 'debit')->where('status', 'completed')->sum('amount'),
            'total_pending' => $query->where('status', 'pending')->sum('net_amount')
        ];
        
        return response()->json([
            'success' => true,
            'data' => $transactions,
            'summary' => $summary
        ]);
    }

    /**
     * Get withdrawal requests
     * GET /vendor/withdrawals
     */
    public function withdrawals(Request $request)
    {
        $user = $request->user();
        $owner = $this->getWalletOwner($user);
        
        $query = WithdrawRequest::where($owner['field'], $owner['id']);
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        $withdrawals = $query->orderBy('created_at', 'desc')->paginate(15);
        
        // Add summary
        $summary = [
            'total_requested' => $query->sum('amount'),
            'total_approved' => (clone $query)->where('status', 'approved')->sum('amount'),
            'total_rejected' => (clone $query)->where('status', 'rejected')->sum('amount'),
            'total_processing' => (clone $query)->where('status', 'processing')->sum('amount')
        ];
        
        return response()->json([
            'success' => true,
            'data' => $withdrawals,
            'summary' => $summary
        ]);
    }

    /**
     * Request withdrawal
     * POST /vendor/wallet/withdraw
     */
    public function withdraw(Request $request)
    {
        $user = $request->user();
        $owner = $this->getWalletOwner($user);
        
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'note' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $wallet = VendorWallet::where($owner['field'], $owner['id'])->first();

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }

        if ($wallet->available_balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient available balance. Available: ₹' . number_format($wallet->available_balance, 2)
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            // Create withdrawal request
            $withdraw = WithdrawRequest::create([
                $owner['field'] => $owner['id'],
                'amount' => $request->amount,
                'bank_account_id' => $request->bank_account_id,
                'note' => $request->note,
                'status' => 'pending',
                'requested_at' => now()
            ]);
            
            // Create debit transaction (pending)
            VendorTransaction::create([
                'vendor_wallet_id' => $wallet->id,
                'vendor_id' => $owner['id'],
                'amount' => $request->amount,
                'net_amount' => $request->amount,
                'type' => 'debit',
                'source' => 'withdrawal',
                'reference_id' => $withdraw->id,
                'description' => "Withdrawal request #{$withdraw->id}",
                'status' => 'pending'
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'data' => [
                    'request_id' => $withdraw->id,
                    'amount' => $request->amount,
                    'status' => 'pending',
                    'expected_processing_days' => '2-3 business days'
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process withdrawal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get settlement summary for vendor
     * GET /vendor/wallet/settlements
     */
    public function settlements(Request $request)
    {
        $user = $request->user();
    $owner = $this->getWalletOwner($user);
    
    // ✅ Remove 'status' condition
    $pendingItems = OrderItem::where('vendor_id', $owner['id'])
        ->where('settlement_status', 'pending')
        // ❌ Remove: ->where('status', 'delivered')
        ->where(function($q) {
            $q->whereNull('eligible_for_settlement_at')
              ->orWhere('eligible_for_settlement_at', '<=', now());
        })
        ->with(['product', 'order'])
        ->get();
        
        // Get settled items
        $settledItems = OrderItem::where('vendor_id', $owner['id'])
            ->where('settlement_status', 'settled')
            ->whereNotNull('settled_at')
            ->with(['product', 'order'])
            ->latest('settled_at')
            ->limit(20)
            ->get();
        
        // Calculate totals
        $pendingAmount = $pendingItems->sum('vendor_payout');
        $settledAmount = $settledItems->sum('vendor_payout');
        
        // Group pending by order
        $pendingByOrder = $pendingItems->groupBy('order_id')->map(function($items, $orderId) {
            $order = $items->first()->order;
            return [
                'order_id' => $orderId,
                'order_number' => $order->order_number,
                'items_count' => $items->count(),
                'total_amount' => $items->sum('vendor_payout'),
                'items' => $items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'vendor_payout' => $item->vendor_payout,
                        'eligible_since' => $item->eligible_for_settlement_at
                    ];
                })
            ];
        })->values();
        
        return response()->json([
            'success' => true,
            'data' => [
                'pending_settlement' => [
                    'total_amount' => round($pendingAmount, 2),
                    'items_count' => $pendingItems->count(),
                    'orders_count' => $pendingByOrder->count(),
                    'by_order' => $pendingByOrder
                ],
                'settled_history' => [
                    'total_amount' => round($settledAmount, 2),
                    'items_count' => $settledItems->count(),
                    'recent_settlements' => $settledItems->map(function($item) {
                        return [
                            'id' => $item->id,
                            'order_number' => $item->order->order_number,
                            'product_name' => $item->product_name,
                            'amount' => $item->vendor_payout,
                            'settled_at' => $item->settled_at
                        ];
                    })
                ]
            ]
        ]);
    }

    /**
     * Get earnings summary by period
     * GET /vendor/wallet/earnings-summary
     */
    public function earningsSummary(Request $request)
    {
        $user = $request->user();
        $owner = $this->getWalletOwner($user);
        
        $period = $request->get('period', 'month'); // week, month, year, all
        
        $query = VendorTransaction::where('vendor_id', $owner['id'])
            ->where('type', 'credit')
            ->where('status', 'completed');
        
        switch ($period) {
            case 'week':
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            case 'month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
            case 'year':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
                break;
            default:
                $startDate = null;
                $endDate = null;
        }
        
        if ($startDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        $summary = [
            'total_earnings' => (clone $query)->sum('net_amount'),
            'total_orders' => (clone $query)->distinct('reference_id')->count('reference_id'),
            'total_items' => (clone $query)->sum('amount') / 100, // Approximate
            'average_order_value' => (clone $query)->avg('net_amount')
        ];
        
        // Get daily breakdown for chart
        $dailyEarnings = (clone $query)
            ->selectRaw('DATE(created_at) as date, SUM(net_amount) as total')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'summary' => $summary,
                'daily_breakdown' => $dailyEarnings,
                'date_range' => $startDate ? [
                    'from' => $startDate->format('Y-m-d'),
                    'to' => $endDate->format('Y-m-d')
                ] : null
            ]
        ]);
    }

    /**
     * Get wallet statement (detailed report)
     * GET /vendor/wallet/statement
     */
    public function statement(Request $request)
    {
        $user = $request->user();
        $owner = $this->getWalletOwner($user);
        
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'format' => 'nullable|in:json,csv'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $wallet = VendorWallet::where($owner['field'], $owner['id'])->first();
        
        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }
        
        $transactions = VendorTransaction::where('vendor_wallet_id', $wallet->id)
            ->whereBetween('created_at', [$request->from_date, $request->to_date])
            ->orderBy('created_at', 'asc')
            ->get();
        
        $openingBalance = VendorTransaction::where('vendor_wallet_id', $wallet->id)
            ->where('created_at', '<', $request->from_date)
            ->where('status', 'completed')
            ->selectRaw('SUM(CASE WHEN type = "credit" THEN net_amount ELSE -amount END) as balance')
            ->value('balance') ?? 0;
        
        $statement = [
            'vendor_name' => $user->vendor ? $user->vendor->store_name : $user->name,
            'period' => [
                'from' => $request->from_date,
                'to' => $request->to_date
            ],
            'opening_balance' => round($openingBalance, 2),
            'transactions' => $transactions->map(function($txn) {
                return [
                    'date' => $txn->created_at->format('Y-m-d H:i:s'),
                    'description' => $txn->description,
                    'type' => $txn->type,
                    'amount' => round($txn->type === 'credit' ? $txn->net_amount : -$txn->amount, 2),
                    'balance' => round($txn->balance_after ?? 0, 2),
                    'reference' => $txn->reference_id
                ];
            }),
            'closing_balance' => round($openingBalance + $transactions->sum(function($txn) {
                return $txn->type === 'credit' ? $txn->net_amount : -$txn->amount;
            }), 2)
        ];
        
        if ($request->get('format') === 'csv') {
            return $this->exportStatementToCsv($statement);
        }
        
        return response()->json([
            'success' => true,
            'data' => $statement
        ]);
    }

    /**
     * Calculate detailed balance breakdown
     */
    private function calculateDetailedBalances($wallet)
{
    // ✅ Remove 'status' condition - use shipment relationship instead
    $pendingSettlement = OrderItem::where('vendor_id', $wallet->vendor_id)
        ->where('settlement_status', 'pending')
        // ❌ Remove: ->where('status', 'delivered')
        ->where(function($q) {
            $q->whereNull('eligible_for_settlement_at')
              ->orWhere('eligible_for_settlement_at', '<=', now());
        })
        ->sum('vendor_payout');
    
    // Calculate processing withdrawals
    $processingWithdrawals = WithdrawRequest::where('vendor_id', $wallet->vendor_id)
        ->where('status', 'pending')
        ->sum('amount');
    
    return [
        'current_balance' => round($wallet->balance, 2),
        'pending_settlement' => round($pendingSettlement, 2),
        'available_for_withdrawal' => round($wallet->available_balance, 2),
        'processing_withdrawals' => round($processingWithdrawals, 2),
        'total_lifetime_earnings' => round($wallet->total_earned, 2),
        'total_withdrawn' => round($wallet->total_withdrawn, 2)
    ];
}

    /**
     * Export statement to CSV
     */
    private function exportStatementToCsv($statement)
    {
        $csvData = [];
        $csvData[] = ['Date', 'Description', 'Type', 'Amount', 'Balance'];
        
        foreach ($statement['transactions'] as $txn) {
            $csvData[] = [
                $txn['date'],
                $txn['description'],
                $txn['type'],
                $txn['amount'],
                $txn['balance']
            ];
        }
        
        $csvData[] = [];
        $csvData[] = ['Opening Balance', '', '', $statement['opening_balance'], ''];
        $csvData[] = ['Closing Balance', '', '', $statement['closing_balance'], ''];
        
        $csv = $this->arrayToCsv($csvData);
        
        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="wallet_statement_' . date('Ymd_His') . '.csv"');
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