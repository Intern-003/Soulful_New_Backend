<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class AdminLogController extends Controller
{
    /**
     * ✅ GET /admin/logs
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')
            ->latest('created_at');

        // 🔍 Filter by user_id
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // 🔍 Filter by action
        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }

        // 🔍 Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // 📄 Pagination
        $logs = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}