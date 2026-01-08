<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view any models.
     * Both employees and agents can access the ticket list, but filtering is applied in queries.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Agents can view all tickets. Employees can only view tickets they created.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->role->isAgent()) {
            return true;
        }

        return $ticket->created_by === $user->id;
    }

    /**
     * Determine whether the user can create models.
     * Both employees and agents can create tickets.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * Only agents can update ticket details.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->role->isAgent();
    }

    /**
     * Determine whether the user can delete the model.
     * Only agents can delete tickets.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->role->isAgent();
    }

    /**
     * Determine whether the user can assign tickets to agents.
     * Only agents can assign tickets.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->role->isAgent();
    }

    /**
     * Determine whether the user can change ticket status.
     * Only agents can change ticket status.
     */
    public function updateStatus(User $user, Ticket $ticket): bool
    {
        return $user->role->isAgent();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Ticket $ticket): bool
    {
        return $user->role->isAgent();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return $user->role->isAgent();
    }
}
