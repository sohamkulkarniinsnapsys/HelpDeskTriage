<?php

namespace App\Http\Controllers;

use App\Http\Requests\FindSimilarTicketsRequest;
use App\Services\TicketSimilarityEngine;
use Illuminate\Http\JsonResponse;

class SimilarTicketController extends Controller
{
    /**
     * Construct controller with similarity engine.
     */
    public function __construct(
        protected TicketSimilarityEngine $similarityEngine
    ) {}

    /**
     * Find tickets similar to a draft ticket being created.
     *
     * This endpoint is called interactively as the user is drafting a ticket.
     * It returns up to 5 similar tickets with relevance scores.
     *
     * Request:
     *   POST /tickets/similar
     *   {
     *     "subject": "VPN Connection Issues",
     *     "description": "Users report cannot connect to corporate VPN",
     *     "category": "network" (optional)
     *   }
     *
     * Response:
     *   [
     *     {
     *       "id": 42,
     *       "subject": "VPN down for East Coast users",
     *       "description_snippet": "Started this morning. East coast office...",
     *       "category": "network",
     *       "status": "open",
     *       "created_at": "2026-01-08T10:30:00Z",
     *       "relevance_score": 0.78
     *     },
     *     ...
     *   ]
     *
     * Authorization:
    * - Agents and employees can query
    * - For similarity, both roles consider all active tickets; payload is
    *   limited to non-sensitive fields.
     *
     * Performance:
     * - Designed for interactive use (< 200ms typical)
     * - Queries limited to 500 candidates
     * - Returns only top 5 results
     * - Should be safe to call frequently with debouncing
     */
    public function find(FindSimilarTicketsRequest $request): JsonResponse
    {
        $similar = $this->similarityEngine->findSimilarTickets(
            user: $request->user(),
            draftSubject: $request->input('subject'),
            draftDescription: $request->input('description'),
            draftCategory: $request->input('category')
        );

        return response()->json($similar);
    }
}
