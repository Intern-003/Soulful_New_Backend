<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    // ===============================
    // ✅ CREATE TICKET
    // ===============================
public function store(Request $request)
{
    $request->validate([
        'subject' => 'required|string|max:255',
        'description' => 'required|string'
    ]);

    // 🚫 CHECK ACTIVE TICKET
    $activeTicket = SupportTicket::where('user_id', Auth::id())
        ->whereIn('status', ['open', 'pending'])
        ->exists();

    if ($activeTicket) {
        return response()->json([
            'success' => false,
            'message' => 'You already have an active ticket. Please wait until it is closed.'
        ], 400);
    }

    // ✅ CREATE TICKET
    $ticket = SupportTicket::create([
        'user_id' => Auth::id(),
        'subject' => $request->subject,
        'description' => $request->description,
        'status' => 'open'
    ]);

    // ✅ FIRST MESSAGE
    SupportTicketReply::create([
        'ticket_id' => $ticket->id,
        'user_id' => Auth::id(),
        'message' => $request->description,
        'type' => 'user'
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Ticket created successfully',
        'data' => $ticket
    ]);
}

    // ===============================
    // ✅ REPLY
    // ===============================
    public function reply(Request $request, $id)
    {
        $ticket = SupportTicket::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $request->validate([
            'message' => 'required|string'
        ]);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
            'type' => 'user'
        ]);

        // optional: mark as open again
        $ticket->update(['status' => 'open']);

        return response()->json([
            'success' => true,
            'message' => 'Reply sent',
            'data' => $reply
        ]);
    }

    // ===============================
    // ✅ LIST USER TICKETS
    // ===============================
    public function index()
    {
        $tickets = SupportTicket::where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    // ===============================
    // ✅ VIEW SINGLE (CHAT)
    // ===============================
    public function show($id)
    {
        $ticket = SupportTicket::where('id', $id)
            ->where('user_id', Auth::id())
            ->with([
                'replies' => function ($q) {
                    $q->orderBy('created_at', 'asc');
                }
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }
}