<?php
// app/Http/Controllers/API/Vendor/VendorCouponController.php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VendorCouponController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId && $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $query = Coupon::with(['vendor'])
            ->where(function ($q) use ($vendorId, $user) {
                if ($vendorId) {
                    $q->where('vendor_id', $vendorId);
                }
                if ($user->role_id === 1) {
                    $q->orWhere('creator_id', $user->id);
                }
            });

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

        // ✅ Add filter for show_on_listing
        if ($request->has('show_on_listing')) {
            $query->where('show_on_listing', $request->show_on_listing === 'true');
        }

        if ($request->has('search')) {
            $query->where('code', 'like', '%' . $request->search . '%');
        }

        $coupons = $query->orderBy('created_at', 'desc')->paginate(15);

        $coupons->getCollection()->transform(function ($coupon) {
            $coupon->usage_percentage = $coupon->usage_limit ?
                round(($coupon->used_count / $coupon->usage_limit) * 100, 2) : 0;
            $coupon->is_expired = $coupon->expiry_date < Carbon::now();
            $coupon->is_active_status = $coupon->status && !$coupon->is_expired;
            return $coupon;
        });

        return response()->json([
            'success' => true,
            'data' => $coupons
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
            'funded_by' => 'sometimes|in:admin,vendor,shared',
            'vendor_share_percentage' => 'nullable|numeric|min:0|max:100',
            'admin_share_percentage' => 'nullable|numeric|min:0|max:100',
            'show_on_listing' => 'sometimes|boolean', // ✅ ADD THIS
        ]);

        if ($request->type === 'percent' && $request->value > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage value cannot exceed 100'
            ], 422);
        }

        $user = Auth::user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId && $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $fundedBy = $request->funded_by ?? 'vendor';

        if ($fundedBy === 'admin' && $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can create admin-funded coupons'
            ], 403);
        }

        // DYNAMIC SHARED COUPON SPLIT LOGIC
        if ($fundedBy === 'shared') {
            if ($request->has('vendor_share_percentage') && $request->has('admin_share_percentage')) {
                $total = ($request->vendor_share_percentage ?? 0) + ($request->admin_share_percentage ?? 0);
                if ($total != 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vendor and admin shares must total 100%'
                    ], 422);
                }
            } elseif ($request->has('vendor_share_percentage')) {
                $request->merge([
                    'admin_share_percentage' => 100 - $request->vendor_share_percentage
                ]);
            } elseif ($request->has('admin_share_percentage')) {
                $request->merge([
                    'vendor_share_percentage' => 100 - $request->admin_share_percentage
                ]);
            } else {
                $request->merge([
                    'vendor_share_percentage' => 50,
                    'admin_share_percentage' => 50
                ]);
            }
        }

        $coupon = Coupon::create([
            'vendor_id' => $vendorId,
            'creator_id' => $user->id,
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
            'funded_by' => $fundedBy,
            'vendor_share_percentage' => $request->vendor_share_percentage ?? ($fundedBy === 'shared' ? 50 : ($fundedBy === 'vendor' ? 100 : 0)),
            'admin_share_percentage' => $request->admin_share_percentage ?? ($fundedBy === 'shared' ? 50 : ($fundedBy === 'admin' ? 100 : 0)),
            'applies_to' => 'all',
            'show_on_listing' => $request->show_on_listing ?? true, // ✅ ADD THIS
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully',
            'data' => $coupon
        ], 201);
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
                'show_on_listing' => 'sometimes|boolean', // ✅ ADD THIS
            ]);

            $coupon->update($request->only(['status', 'show_on_listing']));

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
            'show_on_listing' => 'sometimes|boolean', // ✅ ADD THIS
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

        $data = $request->only([
            'type', 'value',
            'min_order_amount', 'max_discount', 'usage_limit',
            'start_date', 'expiry_date', 'status',
            'vendor_share_percentage', 'admin_share_percentage',
            'show_on_listing', // ✅ ADD THIS
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

 
    public function show($id)
    {
        $user = Auth::user();
        $vendorId = optional($user->vendor)->id;

        $coupon = Coupon::with([
            'vendor',
            'usages' => function ($q) {
                $q->with('user')->latest()->limit(10);
            }
        ])->findOrFail($id);

        if ($coupon->vendor_id !== $vendorId && $coupon->creator_id !== $user->id && $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this coupon'
            ], 403);
        }

        $coupon->usage_percentage = $coupon->usage_limit ?
            round(($coupon->used_count / $coupon->usage_limit) * 100, 2) : 0;
        $coupon->is_expired = $coupon->expiry_date < Carbon::now();
        $coupon->total_savings = $coupon->usages->sum('discount_amount');
        $coupon->total_orders = $coupon->usages->count();

        return response()->json([
            'success' => true,
            'data' => $coupon
        ]);
    }

   
    public function destroy($id)
    {
        $user = Auth::user();
        $vendorId = optional($user->vendor)->id;
        $coupon = Coupon::findOrFail($id);

        if ($coupon->vendor_id !== $vendorId && $coupon->creator_id !== $user->id && $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this coupon'
            ], 403);
        }

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

    public function toggleStatus($id)
    {
        $user = Auth::user();
        $vendorId = optional($user->vendor)->id;
        $coupon = Coupon::findOrFail($id);

        if ($coupon->vendor_id !== $vendorId && $coupon->creator_id !== $user->id && $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

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

    public function stats(Request $request)
    {
        $user = Auth::user();
        $vendorId = optional($user->vendor)->id;
        $query = Coupon::where('vendor_id', $vendorId);
        $now = Carbon::now();

        $stats = [
            'total_coupons' => (clone $query)->count(),
            'active_coupons' => (clone $query)->where('status', true)
                ->where('start_date', '<=', $now)
                ->where('expiry_date', '>=', $now)
                ->count(),
            'expired_coupons' => (clone $query)->where('expiry_date', '<', $now)->count(),
            'inactive_coupons' => (clone $query)->where('status', false)->count(),
            'total_used' => (clone $query)->sum('used_count'),
            'total_savings' => CouponUsage::whereHas('coupon', function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            })->sum('discount_amount'),
            'most_used_coupon' => (clone $query)->orderBy('used_count', 'desc')->first(),
            'coupons_nearing_expiry' => (clone $query)
                ->where('status', true)
                ->where('expiry_date', '>=', $now)
                ->where('expiry_date', '<=', $now->copy()->addDays(7))
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function usages($id, Request $request)
    {
        $user = Auth::user();
        $vendorId = optional($user->vendor)->id;
        $coupon = Coupon::findOrFail($id);

        if ($coupon->vendor_id !== $vendorId && $coupon->creator_id !== $user->id && $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $usages = CouponUsage::with(['user', 'order'])
            ->where('coupon_id', $coupon->id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $usages->map(function ($usage) {
                return [
                    'id' => $usage->id,
                    'user' => [
                        'id' => $usage->user->id,
                        'name' => $usage->user->name,
                        'email' => $usage->user->email
                    ],
                    'order_number' => $usage->order->order_number,
                    'discount_amount' => $usage->discount_amount,
                    'breakdown' => $usage->breakdown,
                    'used_at' => $usage->created_at->format('Y-m-d H:i:s')
                ];
            }),
            'pagination' => [
                'current_page' => $usages->currentPage(),
                'last_page' => $usages->lastPage(),
                'total' => $usages->total()
            ]
        ]);
    }
}