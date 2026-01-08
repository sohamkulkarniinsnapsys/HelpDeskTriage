<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketPageController extends Controller
{
    public function __construct(private TicketService $ticketService)
    {
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Ticket::class);

        return view('tickets.create', [
            'categories' => TicketCategory::cases(),
            'severities' => range(1, 5),
            'user' => $request->user(),
        ]);
    }

    public function myTickets(Request $request)
    {
        $user = $request->user();

        return $this->renderList($request, $user, 'my');
    }

    public function allTickets(Request $request)
    {
        $user = $request->user();

        if (!$user->role->isAgent()) {
            abort(403, 'Agents only.');
        }

        return $this->renderList($request, $user, 'all');
    }

    public function show(Request $request, Ticket $ticket)
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['creator', 'assignee', 'attachments']);

        $agents = User::query()
            ->where('role', Role::Agent)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('tickets.show', [
            'ticket' => $ticket,
            'statuses' => TicketStatus::cases(),
            'categories' => TicketCategory::cases(),
            'agents' => $agents,
            'user' => $request->user(),
        ]);
    }

    protected function renderList(Request $request, User $user, string $context)
    {
        $filters = [
            'status' => $request->input('status'),
            'category' => $request->input('category'),
            'severity' => $request->input('severity'),
            'search' => $request->input('search'),
            'unassigned' => $request->boolean('unassigned'),
            'per_page' => $request->input('per_page', 10),
        ];

        /** @var LengthAwarePaginator $tickets */
        $tickets = $this->ticketService->getTickets(
            user: $user,
            status: $filters['status'],
            category: $filters['category'],
            severity: $filters['severity'] ? (int) $filters['severity'] : null,
            search: $filters['search'],
            unassigned: $user->role->isAgent() ? $filters['unassigned'] : null,
            perPage: (int) $filters['per_page'],
        );

        return view('tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'context' => $context,
            'user' => $user,
            'categories' => TicketCategory::cases(),
            'statuses' => TicketStatus::cases(),
        ]);
    }
}
