<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    // Create ticket
    public function store(Request $request) {
        $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string'
        ]);

        $ticket = SupportTicket::create([
            'user_id' => Auth::id(),
            'subject' => $request->subject,
            'description' => $request->description,
        ]);

        return response()->json(['success'=>true, 'ticket'=>$ticket]);
    }

    // Reply to ticket
    public function reply(Request $request, $id) {
        $ticket = SupportTicket::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        $request->validate(['message'=>'required|string']);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
            'type' => 'user'
        ]);

        return response()->json(['success'=>true, 'reply'=>$reply]);
    }

    // List own tickets
    public function index() {
        $tickets = SupportTicket::where('user_id', Auth::id())->with('replies')->get();
        return response()->json($tickets);
    }

    // View single ticket with replies
    public function show($id) {
        $ticket = SupportTicket::where('id', $id)->where('user_id', Auth::id())->with('replies')->firstOrFail();
        return response()->json($ticket);
    }
}