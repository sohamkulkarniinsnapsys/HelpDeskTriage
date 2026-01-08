<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets.
     * Agents see all tickets. Employees see only their own.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Ticket::class);

        $tickets = Ticket::query()
            ->visibleTo($request->user())
            ->with(['creator', 'assignee'])
            ->recent()
            ->get();

        return response()->json($tickets);
    }

    /**
     * Display the specified ticket.
     * Authorization enforced - employees can only view their own tickets.
     */
    public function show(Ticket $ticket)
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['creator', 'assignee', 'attachments']);

        return response()->json($ticket);
    }

    /**
     * Store a newly created ticket.
     * Both employees and agents can create tickets.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Ticket::class);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string|in:access,hardware,network,bug,other',
            'severity' => 'required|integer|between:1,5',
        ]);

        $ticket = Ticket::create([
            ...$validated,
            'status' => TicketStatus::Open,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($ticket, 201);
    }

    /**
     * Update the specified ticket.
     * Only agents can update tickets.
     */
    public function update(Request $request, Ticket $ticket)
    {
        Gate::authorize('update', $ticket);

        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category' => 'sometimes|string|in:access,hardware,network,bug,other',
            'severity' => 'sometimes|integer|between:1,5',
        ]);

        $ticket->update($validated);

        return response()->json($ticket);
    }

    /**
     * Assign a ticket to an agent.
     * Only agents can assign tickets.
     */
    public function assign(Request $request, Ticket $ticket)
    {
        Gate::authorize('assign', $ticket);

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        // Verify the assigned user is an agent
        if ($validated['assigned_to']) {
            $assignee = User::findOrFail($validated['assigned_to']);
            if (!$assignee->role->isAgent()) {
                abort(422, 'Tickets can only be assigned to agents.');
            }
        }

        $ticket->update([
            'assigned_to' => $validated['assigned_to'],
            'status' => $validated['assigned_to'] ? TicketStatus::InProgress : TicketStatus::Open,
        ]);

        return response()->json($ticket);
    }

    /**
     * Update the status of a ticket.
     * Only agents can change ticket status.
     */
    public function updateStatus(Request $request, Ticket $ticket)
    {
        Gate::authorize('updateStatus', $ticket);

        $validated = $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed',
        ]);

        $ticket->update([
            'status' => $validated['status'],
        ]);

        return response()->json($ticket);
    }

    /**
     * Remove the specified ticket.
     * Only agents can delete tickets.
     */
    public function destroy(Ticket $ticket)
    {
        Gate::authorize('delete', $ticket);

        $ticket->delete();

        return response()->json(null, 204);
    }
}
