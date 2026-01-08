<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\Ticket;
use App\Services\AttachmentService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Display a listing of tickets visible to the authenticated user.
     * Supports filtering by status, category, search, and unassigned.
     */
    public function index(Request $request): LengthAwarePaginator
    {
        Gate::authorize('viewAny', Ticket::class);

        $severity = $request->input('severity');

        $tickets = $this->ticketService->getTickets(
            user: $request->user(),
            status: $request->input('status'),
            category: $request->input('category'),
            severity: $severity ? (int) $severity : null,
            search: $request->input('search'),
            unassigned: $request->boolean('unassigned'),
            perPage: $request->input('per_page', 15)
        );

        return $tickets;
    }

    /**
     * Display the specified ticket with all relationships.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['creator', 'assignee', 'attachments']);

        return response()->json($ticket);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request)
    {
        $validated = $request->validated();
        
        $ticket = $this->ticketService->createTicket(
            $request->user(),
            $request->safe()->except('attachments')
        );

        // Handle attachments if provided
        if ($request->hasFile('attachments')) {
            $this->attachmentService->uploadAttachments(
                $ticket,
                $request->file('attachments')
            );
        }

        $ticket->load(['creator', 'attachments']);

        if ($request->wantsJson()) {
            return response()->json($ticket, 201);
        }

        return redirect()
            ->route('tickets.view', $ticket)
            ->with('status', 'Ticket created');
    }

    /**
     * Update the specified ticket.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $ticket = $this->ticketService->updateTicket(
            $ticket,
            $request->validated()
        );

        $ticket->load(['creator', 'assignee', 'attachments']);

        return response()->json($ticket);
    }

    /**
     * Assign a ticket to an agent or unassign it.
     */
    public function assign(AssignTicketRequest $request, Ticket $ticket)
    {
        $ticket = $this->ticketService->assignTicket(
            $ticket,
            $request->validated('assigned_to')
        );

        $ticket->load(['creator', 'assignee']);

        if ($request->wantsJson()) {
            return response()->json($ticket);
        }

        return redirect()
            ->route('tickets.view', $ticket)
            ->with('status', 'Assignment updated');
    }

    /**
     * Update the status of a ticket.
     */
    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket)
    {
        $ticket = $this->ticketService->updateStatus(
            $ticket,
            TicketStatus::from($request->validated('status'))
        );

        $ticket->load(['creator', 'assignee']);

        if ($request->wantsJson()) {
            return response()->json($ticket);
        }

        return redirect()
            ->route('tickets.view', $ticket)
            ->with('status', 'Status updated');
    }

    /**
     * Get ticket statistics for the authenticated user.
     */
    public function statistics(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        $stats = $this->ticketService->getStatistics($request->user());

        return response()->json($stats);
    }

    /**
     * Remove the specified ticket.
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        Gate::authorize('delete', $ticket);

        $ticket->delete();

        return response()->json(null, 204);
    }
}
