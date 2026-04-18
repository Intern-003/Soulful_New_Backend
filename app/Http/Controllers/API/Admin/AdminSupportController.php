<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Support\Facades\Auth;

class AdminSupportController extends Controller
{
    // ===============================
    // ✅ ALL TICKETS
    // ===============================
    public function index(Request $request)
    {
        $query = SupportTicket::with('user');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $tickets = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    // ===============================
    // ✅ SINGLE TICKET (CHAT)
    // ===============================
    public function show($id)
    {
        $ticket = SupportTicket::with([
            'user',
            'replies' => function ($q) {
                $q->orderBy('created_at', 'asc');
            }
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }

    // ===============================
    // ✅ ADMIN REPLY
    // ===============================
    public function reply(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $ticket = SupportTicket::findOrFail($id);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(), // ✅ FIXED (no null)
            'message' => $request->message,
            'type' => 'admin'
        ]);

        // update status
        $ticket->update([
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reply sent',
            'data' => $reply
        ]);
    }

    // ===============================
    // ✅ UPDATE STATUS
    // ===============================
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:open,pending,closed'
        ]);

        $ticket = SupportTicket::findOrFail($id);

        $ticket->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
            'data' => $ticket
        ]);
    }
}