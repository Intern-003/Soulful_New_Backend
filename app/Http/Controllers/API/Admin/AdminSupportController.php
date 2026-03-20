<?php
namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;

class AdminSupportController extends Controller
{
    // List all tickets (with optional filters)
    public function index(Request $request)
    {
        $query = SupportTicket::with('user', 'replies');

        // Optional: filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'tickets' => $tickets
        ]);
    }

    // View a single ticket with replies
    public function show($id)
    {
        $ticket = SupportTicket::with('user', 'replies')->findOrFail($id);

        return response()->json([
            'success' => true,
            'ticket' => $ticket
        ]);
    }

    // Admin reply to a ticket
    public function reply(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $request->validate([
            'message' => 'required|string'
        ]);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => null, // admin reply, so null
            'message' => $request->message,
            'type' => 'admin'
        ]);

        // Optionally, set ticket status to 'pending' if it's still open
        if ($ticket->status == 'open') {
            $ticket->status = 'pending';
            $ticket->save();
        }

        return response()->json([
            'success' => true,
            'reply' => $reply
        ]);
    }

    // Change ticket status (open, pending, closed)
    public function updateStatus(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $request->validate([
            'status' => 'required|in:open,pending,closed'
        ]);

        $ticket->status = $request->status;
        $ticket->save();

        return response()->json([
            'success' => true,
            'ticket' => $ticket
        ]);
    }
}