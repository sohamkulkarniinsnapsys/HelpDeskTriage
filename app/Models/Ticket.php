<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subject',
        'description',
        'category',
        'severity',
        'status',
        'created_by',
        'assigned_to',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'status' => TicketStatus::class,
            'severity' => 'integer',
        ];
    }

    /**
     * Get the user who created this ticket.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the agent assigned to this ticket.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the attachments for this ticket.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Scope a query to only include tickets visible to the given user.
     * Agents see all tickets. Employees see only their own tickets.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role->isAgent()) {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }

    /**
     * Scope a query to only include unassigned tickets.
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Scope a query to only include tickets assigned to a specific user.
     */
    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where('assigned_to', $user->id);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeWithStatus(Builder $query, TicketStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeWithCategory(Builder $query, TicketCategory $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to order by most recent first.
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }
}
