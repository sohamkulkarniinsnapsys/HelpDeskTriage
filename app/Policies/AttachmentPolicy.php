<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy
{
    /**
     * Determine whether the user can view any models.
     * Access is based on the parent ticket's authorization.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Agents can view all attachments. Employees can only view attachments
     * belonging to tickets they created.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        $attachment->loadMissing('ticket');

        if ($user->role->isAgent()) {
            return true;
        }

        return $attachment->ticket->created_by === $user->id;
    }

    /**
     * Determine whether the user can create models.
     * Users can create attachments if they can view the parent ticket.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * Only agents can update attachments.
     */
    public function update(User $user, Attachment $attachment): bool
    {
        return $user->role->isAgent();
    }

    /**
     * Determine whether the user can delete the model.
     * Agents can delete any attachment. Employees can delete attachments
     * on tickets they created.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        $attachment->loadMissing('ticket');

        if ($user->role->isAgent()) {
            return true;
        }

        return $attachment->ticket->created_by === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attachment $attachment): bool
    {
        return $user->role->isAgent();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attachment $attachment): bool
    {
        return $user->role->isAgent();
    }
}
