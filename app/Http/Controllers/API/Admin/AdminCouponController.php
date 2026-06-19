<?php
// app/Http/Controllers/API/Admin/AdminCouponController.php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AdminCouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::with(['vendor', 'creator']);

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->has('funded_by')) {
            $query->where('funded_by', $request->funded_by);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('status', true)
                    ->where('start_date', '<=', Carbon::now())
                    ->where('expiry_date', '>=', Carbon::now());
            } elseif ($request->status === 'expired') {
                $query->where('expiry_date', '<', Carbon::now());
            } elseif ($request->status === 'inactive') {
                $query->where('status', false);
            }
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%");
            });
        }

        $coupons = $query->latest()->paginate(20);

        foreach ($coupons as $coupon) {
            $coupon->usage_percentage = $coupon->usage_limit ? 
                round(($coupon->used_count / $coupon->usage_limit) * 100, 2) : 0;
            $coupon->total_savings = $coupon->usages->sum('discount_amount');
            $coupon->unique_users = $coupon->usages->unique('user_id')->count();
            $coupon->status_badge = $this->getStatusBadge($coupon);
        }

        return response()->json([
            'success' => true,
            'data' => $coupons,
            'filters' => $request->all()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'expiry_date' => 'required|date|after:start_date',
            'funded_by' => 'required|in:admin,vendor,shared',
            'vendor_id' => 'required_if:funded_by,vendor|nullable|exists:vendors,id',
            'vendor_share_percentage' => 'nullable|numeric|min:0|max:100', // ✅ Made nullable
            'admin_share_percentage' => 'nullable|numeric|min:0|max:100', // ✅ Made nullable
            'applies_to' => 'sometimes|in:all,vendor,category,product',
            'category_id' => 'required_if:applies_to,category|nullable|exists:categories,id',
            'product_id' => 'required_if:applies_to,product|nullable|exists:products,id',
            'applicable_vendors' => 'nullable|array',
            'applicable_vendors.*' => 'exists:vendors,id',
            'show_on_listing' => 'sometimes|boolean',
        ]);

        if ($request->type === 'percent' && $request->value > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage value cannot exceed 100'
            ], 422);
        }

        // ✅ DYNAMIC SHARED COUPON SPLIT LOGIC
        if ($request->funded_by === 'shared') {
            // If both percentages are provided, validate they total 100
            if ($request->has('vendor_share_percentage') && $request->has('admin_share_percentage')) {
                $total = ($request->vendor_share_percentage ?? 0) + ($request->admin_share_percentage ?? 0);
                if ($total != 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vendor and admin shares must total 100%'
                    ], 422);
                }
            } 
            // If only one is provided, calculate the other
            elseif ($request->has('vendor_share_percentage')) {
                $request->merge([
                    'admin_share_percentage' => 100 - $request->vendor_share_percentage
                ]);
            }
            elseif ($request->has('admin_share_percentage')) {
                $request->merge([
                    'vendor_share_percentage' => 100 - $request->admin_share_percentage
                ]);
            }
            // If neither is provided, default to 50/50
            else {
                $request->merge([
                    'vendor_share_percentage' => 50,
                    'admin_share_percentage' => 50
                ]);
            }
        }

        if ($request->funded_by === 'vendor' && !$request->vendor_id) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor ID is required for vendor-funded coupons'
            ], 422);
        }

        $coupon = Coupon::create([
            'vendor_id' => $request->funded_by === 'vendor' ? $request->vendor_id : null,
            'creator_id' => auth()->id(),
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'value' => $request->value,
            'min_order_amount' => $request->min_order_amount,
            'max_discount' => $request->max_discount,
            'usage_limit' => $request->usage_limit,
            'used_count' => 0,
            'status' => true,
            'start_date' => $request->start_date,
            'expiry_date' => $request->expiry_date,
            'funded_by' => $request->funded_by,
            'vendor_share_percentage' => $request->vendor_share_percentage,
            'admin_share_percentage' => $request->admin_share_percentage,
            'applies_to' => $request->applies_to ?? 'all',
            'category_id' => $request->category_id,
            'product_id' => $request->product_id,
            'applicable_vendors' => $request->applicable_vendors,
            'show_on_listing' => $request->show_on_listing ?? true,
            
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin coupon created successfully',
            'data' => $coupon
        ], 201);
    }

    public function show($id)
    {
        $coupon = Coupon::with(['vendor', 'creator', 'usages.user', 'usages.order'])
            ->findOrFail($id);

        $stats = [
            'total_usage' => $coupon->usages->count(),
            'total_savings' => $coupon->usages->sum('discount_amount'),
            'unique_users' => $coupon->usages->unique('user_id')->count(),
            'average_discount' => $coupon->usages->avg('discount_amount'),
            'usage_by_day' => $coupon->usages->groupBy(function($usage) {
                return $usage->created_at->format('Y-m-d');
            })->map->count(),
            'top_users' => $coupon->usages->groupBy('user_id')
                ->map(function($usages) {
                    return [
                        'user' => $usages->first()->user->name,
                        'count' => $usages->count(),
                        'total_discount' => $usages->sum('discount_amount')
                    ];
                })->sortByDesc('count')->take(5)->values()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'coupon' => $coupon,
                'statistics' => $stats
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $vendorId = optional($user->vendor)->id;
        $coupon = Coupon::findOrFail($id);

        if ($coupon->vendor_id !== $vendorId && $coupon->creator_id !== $user->id && $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this coupon'
            ], 403);
        }

        if ($coupon->used_count > 0) {
            $request->validate([
                'status' => 'sometimes|boolean',
                'show_on_listing' => 'sometimes|boolean'
            ]);

            $coupon->update($request->only(['status','show_on_listing']));

            return response()->json([
                'success' => true,
                'message' => 'Coupon updated successfully (limited fields)',
                'data' => $coupon
            ]);
        }

        $request->validate([
            'code' => 'sometimes|string|unique:coupons,code,' . $id,
            'type' => 'sometimes|in:fixed,percent',
            'value' => 'sometimes|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|date',
            'expiry_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|boolean',
            'vendor_share_percentage' => 'nullable|numeric|min:0|max:100',
            'admin_share_percentage' => 'nullable|numeric|min:0|max:100',
            'show_on_listing' => 'sometimes|boolean',
        ]);

        if ($request->has('type') && $request->type === 'percent') {
            $value = $request->value ?? $coupon->value;
            if ($value > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Percentage cannot exceed 100'
                ], 422);
            }
        }

        // ✅ Update split percentages if provided
        $data = $request->only([
            'type', 'value',
            'min_order_amount', 'max_discount', 'usage_limit',
            'start_date', 'expiry_date', 'status',
            'vendor_share_percentage', 'admin_share_percentage' ,'show_on_listing'
        ]);

        if ($request->has('code')) {
            $data['code'] = strtoupper($request->code);
        }

        $coupon->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'data' => $coupon
        ]);
    }

    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);

        if ($coupon->used_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete coupon that has been used. Deactivate it instead.'
            ], 422);
        }

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully'
        ]);
    }

    public function stats(Request $request)
    {
        $now = Carbon::now();

        $stats = [
            'total_coupons' => Coupon::count(),
            'active_coupons' => Coupon::where('status', true)
                ->where('start_date', '<=', $now)
                ->where('expiry_date', '>=', $now)
                ->count(),
            'expired_coupons' => Coupon::where('expiry_date', '<', $now)->count(),
            'inactive_coupons' => Coupon::where('status', false)->count(),
            'total_used' => Coupon::sum('used_count'),
            'total_savings' => CouponUsage::sum('discount_amount'),
            'by_funding_type' => [
                'admin_funded' => Coupon::where('funded_by', 'admin')->count(),
                'vendor_funded' => Coupon::where('funded_by', 'vendor')->count(),
                'shared' => Coupon::where('funded_by', 'shared')->count()
            ],
            'by_discount_type' => [
                'percentage' => Coupon::where('type', 'percent')->count(),
                'fixed' => Coupon::where('type', 'fixed')->count()
            ],
            'top_performing_coupons' => Coupon::with('vendor')
                ->orderBy('used_count', 'desc')
                ->limit(5)
                ->get(['id', 'code', 'used_count', 'type', 'value']),
            'recent_coupons' => Coupon::latest()->limit(5)->get(['id', 'code', 'created_at', 'status'])
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function analytics(Request $request)
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'group_by' => 'nullable|in:day,week,month'
        ]);

        $fromDate = $request->from_date ?? Carbon::now()->subDays(30);
        $toDate = $request->to_date ?? Carbon::now();
        $groupBy = $request->group_by ?? 'day';

        $format = $groupBy === 'day' ? '%Y-%m-%d' : ($groupBy === 'week' ? '%Y-%u' : '%Y-%m');

        $usageData = CouponUsage::whereBetween('created_at', [$fromDate, $toDate])
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as period, 
                         COUNT(*) as usage_count,
                         SUM(discount_amount) as total_discount,
                         COUNT(DISTINCT user_id) as unique_users,
                         COUNT(DISTINCT coupon_id) as unique_coupons")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                    'group_by' => $groupBy
                ],
                'usage_trend' => $usageData,
                'summary' => [
                    'total_usage' => $usageData->sum('usage_count'),
                    'total_discount' => $usageData->sum('total_discount'),
                    'avg_daily_usage' => round($usageData->avg('usage_count'), 2),
                    'unique_users' => $usageData->sum('unique_users')
                ]
            ]
        ]);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'coupon_ids' => 'required|array',
            'coupon_ids.*' => 'exists:coupons,id',
            'action' => 'required|in:activate,deactivate,delete'
        ]);

        $coupons = Coupon::whereIn('id', $request->coupon_ids)->get();

        if ($request->action === 'delete') {
            $usedCoupons = $coupons->filter(fn($c) => $c->used_count > 0);
            if ($usedCoupons->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete coupons that have been used',
                    'used_coupon_ids' => $usedCoupons->pluck('id')
                ], 422);
            }
            Coupon::whereIn('id', $request->coupon_ids)->delete();
            $message = count($request->coupon_ids) . ' coupons deleted';
        } else {
            $status = $request->action === 'activate';
            Coupon::whereIn('id', $request->coupon_ids)->update(['status' => $status]);
            $message = count($request->coupon_ids) . ' coupons ' . ($status ? 'activated' : 'deactivated');
        }

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }

    public function export(Request $request)
    {
        $query = Coupon::with(['vendor', 'creator']);

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        if ($request->has('funded_by')) {
            $query->where('funded_by', $request->funded_by);
        }
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('status', true)
                    ->where('start_date', '<=', Carbon::now())
                    ->where('expiry_date', '>=', Carbon::now());
            } elseif ($request->status === 'expired') {
                $query->where('expiry_date', '<', Carbon::now());
            }
        }

        $coupons = $query->get();

        $csvData = $coupons->map(function($coupon) {
            return [
                'ID' => $coupon->id,
                'Code' => $coupon->code,
                'Value' => $coupon->value,
                'Funded By' => $coupon->funded_by,
                'Vendor Share %' => $coupon->vendor_share_percentage ?? 'N/A',
                'Admin Share %' => $coupon->admin_share_percentage ?? 'N/A',
                'Vendor' => $coupon->vendor ? $coupon->vendor->store_name : 'N/A',
                'Min Order' => $coupon->min_order_amount,
                'Max Discount' => $coupon->max_discount,
                'Usage Limit' => $coupon->usage_limit,
                'Used Count' => $coupon->used_count,
                'Status' => $coupon->status ? 'Active' : 'Inactive',
                'Starts At' => $coupon->start_date->format('Y-m-d'),
                'Expires At' => $coupon->expiry_date->format('Y-m-d'),
                'Created At' => $coupon->created_at->format('Y-m-d H:i:s')
            ];
        });

        $csv = $this->arrayToCsv($csvData->toArray());

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="coupons_export_' . now()->format('Ymd_His') . '.csv"');
    }

    private function getStatusBadge($coupon)
    {
        if (!$coupon->status) return ['text' => 'Inactive', 'color' => 'gray'];
        if ($coupon->expiry_date < Carbon::now()) return ['text' => 'Expired', 'color' => 'red'];
        if ($coupon->start_date > Carbon::now()) return ['text' => 'Scheduled', 'color' => 'yellow'];
        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return ['text' => 'Used Up', 'color' => 'orange'];
        }
        return ['text' => 'Active', 'color' => 'green'];
    }

    private function arrayToCsv($data)
    {
        $output = fopen('php://temp', 'r+');
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        rewind($output);
        return stream_get_contents($output);
    }

    public function toggleStatus($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['status' => !$coupon->status]);

        return response()->json([
            'success' => true,
            'message' => $coupon->status ? 'Coupon activated' : 'Coupon deactivated',
            'data' => [
                'id' => $coupon->id,
                'status' => $coupon->status
            ]
        ]);
    }
}