<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TicketService
{
    /**
     * Get paginated tickets visible to the user with optional filters.
     */
    public function getTickets(
        User $user,
        ?string $status = null,
        ?string $category = null,
        ?int $severity = null,
        ?string $search = null,
        ?bool $unassigned = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Ticket::query()
            ->visibleTo($user)
            ->with(['creator', 'assignee'])
            ->withCount('attachments');

        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($severity) {
            $query->where('severity', $severity);
        }

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($unassigned !== null && $unassigned) {
            $query->whereNull('assigned_to');
        }

        return $query->recent()->paginate($perPage);
    }

    /**
     * Create a new ticket.
     */
    public function createTicket(User $creator, array $data): Ticket
    {
        return Ticket::create([
            'subject' => $data['subject'],
            'description' => $data['description'],
            'category' => $data['category'],
            'severity' => $data['severity'],
            'status' => TicketStatus::Open,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * Update an existing ticket.
     */
    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);
        return $ticket->fresh();
    }

    /**
     * Assign a ticket to an agent.
     */
    public function assignTicket(Ticket $ticket, ?int $agentId): Ticket
    {
        $ticket->update([
            'assigned_to' => $agentId,
            'status' => $agentId 
                ? TicketStatus::InProgress 
                : TicketStatus::Open,
        ]);

        return $ticket->fresh(['assignee']);
    }

    /**
     * Update the status of a ticket.
     */
    public function updateStatus(Ticket $ticket, TicketStatus $status): Ticket
    {
        $ticket->update(['status' => $status]);
        return $ticket->fresh();
    }

    /**
     * Get ticket statistics for a user.
     */
    public function getStatistics(User $user): array
    {
        $query = Ticket::visibleTo($user);

        return [
            'total' => $query->count(),
            'open' => (clone $query)->where('status', TicketStatus::Open)->count(),
            'in_progress' => (clone $query)->where('status', TicketStatus::InProgress)->count(),
            'resolved' => (clone $query)->where('status', TicketStatus::Resolved)->count(),
            'closed' => (clone $query)->where('status', TicketStatus::Closed)->count(),
            'unassigned' => $user->role->isAgent() 
                ? (clone $query)->whereNull('assigned_to')->count() 
                : null,
        ];
    }
}
