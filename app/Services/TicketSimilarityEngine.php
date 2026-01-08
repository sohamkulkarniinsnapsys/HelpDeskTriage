<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * TicketSimilarityEngine
 *
 * Production-grade deterministic similarity detection for helpdesk tickets.
 * Uses a multi-stage pipeline to suggest similar tickets based on text overlap.
 *
 * Pipeline stages:
 * 1. shortlistCandidates() - Database narrowing with aggressive filtering
 * 2. normalizeText() / tokenize() - Text preprocessing
 * 3. buildFeatures() - Feature extraction from tickets
 * 4. scoreCandidate() - Numeric similarity scoring
 * 5. rankAndFilter() - Top-5 ranking with threshold
 * 6. shapeResults() - Safe response formatting
 *
 * All steps are designed for clarity, explainability, and production performance.
 * See "Future Lane 2 Extension Point" at end of class for embedding integration design.
 */
class TicketSimilarityEngine
{
    // ==================== Configuration Constants ====================

    /**
     * How many days back to look for similar tickets.
     * Older tickets are excluded because:
     * - They're less relevant to current issues
     * - They may reference outdated configurations
     * - Recent patterns are more actionable
     *
     * @var int Days of recency window
     */
    private const RECENCY_DAYS = 90;

    /**
     * Maximum candidates to load from database for scoring.
     * Acts as a circuit-breaker to prevent huge memory use or slow scoring.
     * Must be >> TOP_RESULTS to allow proper ranking.
     *
     * @var int Hard limit on candidates before scoring
     */
    private const MAX_CANDIDATES = 500;

    /**
     * Weight applied to subject similarity in final score.
     * Subject is the title/headline and carries high semantic weight.
     * Range: 0.0 to 1.0
     *
     * @var float Subject weight multiplier
     */
    private const WEIGHT_SUBJECT = 0.65;

    /**
     * Weight applied to description similarity in final score.
     * Description provides context but is often verbose and noisy.
     * Range: 0.0 to 1.0
     *
     * @var float Description weight multiplier
     */
    private const WEIGHT_DESCRIPTION = 0.35;

    /**
     * Minimum relevance score (0.0 to 1.0) to include in results.
     * Filters out weak matches and false positives.
     * Set low (0.05) to be permissive with short text and small datasets.
     * Real-world data has more overlap and will naturally score higher.
     *
     * @var float Minimum threshold for inclusion
     */
    private const MIN_RELEVANCE_SCORE = 0.05;

    /**
     * Number of results to return to the client.
     * Limited to top 5 for clarity and to prevent UI overwhelm.
     *
     * @var int Maximum results returned
     */
    private const TOP_RESULTS = 5;

    /**
     * Minimum token length (characters) to include in feature sets.
     * Filters noise like "a", "an", "is" that aren't handled by stop-words.
     * Range: 2-4 recommended
     *
     * @var int Minimum token length
     */
    private const MIN_TOKEN_LENGTH = 2;

    /**
     * Category must match exactly for a ticket to be considered similar.
     * Set to false to allow cross-category suggestions.
     * True enforces topical coherence.
     *
     * @var bool Enforce category matching
     */
    private const REQUIRE_CATEGORY_MATCH = false;

    // ==================== Private Properties ====================

    /**
     * Common stop-words to exclude from token sets.
     * Reduces noise and focuses on domain-specific content.
     *
     * @var array<string> Stop-words to filter
     */
    private array $stopwords = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
        'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'or', 'that',
        'the', 'to', 'was', 'will', 'with', 'the', 'this', 'but', 'not',
        'if', 'can', 'have', 'we', 'they', 'their', 'what', 'which', 'who',
    ];

    // ==================== Public API ====================

    /**
    * Find similar tickets for a draft ticket being created.
    *
    * Main orchestrator method. Calls the pipeline stages in sequence.
    * For similarity purposes, both agents and employees consider all recent
    * active tickets; only a minimal, non-sensitive subset of fields is
    * returned.
     *
     * @param User $user Authenticated user (for authorization)
     * @param string $draftSubject Subject of the draft ticket
     * @param string $draftDescription Description of the draft ticket
     * @param string|null $draftCategory Optional category to match
     * @return array<array> Array of similar tickets with metadata
     */
    public function findSimilarTickets(
        User $user,
        string $draftSubject,
        string $draftDescription,
        ?string $draftCategory = null
    ): array {
        // Stage 1: Narrow search space aggressively
        $candidates = $this->shortlistCandidates($user, $draftCategory);

        // If no candidates, return empty result
        if ($candidates->isEmpty()) {
            return [];
        }

        // Stage 2 & 3: Extract features from draft (subject and description separately)
        $draftFeatures = $this->buildFeatures($draftSubject, $draftDescription);

        // If draft has no meaningful tokens, return empty result
        if (empty($draftFeatures['subject_tokens']) && empty($draftFeatures['description_tokens'])) {
            return [];
        }

        // Stage 4: Build features for each candidate and score
        $scored = $candidates->map(function (Ticket $ticket) use ($draftFeatures) {
            $candidateFeatures = $this->buildFeatures($ticket->subject, $ticket->description);
            $score = $this->scoreCandidate($draftFeatures, $candidateFeatures);

            return [
                'ticket' => $ticket,
                'score' => $score,
            ];
        })->toBase(); // Convert to base Collection

        // Stage 5: Shape results for safe client consumption BEFORE filtering
        // This ensures we have all fields even if some get filtered later
        $shaped = $this->shapeResults($scored);

        // Stage 6: Filter by minimum relevance threshold and rank
        $filtered = collect($shaped)
            ->filter(fn (array $item) => $item['relevance_score'] >= self::MIN_RELEVANCE_SCORE)
            ->sortByDesc('relevance_score')
            ->take(self::TOP_RESULTS)
            ->values()
            ->toArray();

        return $filtered;
    }

    // ==================== Stage 1: Candidate Shortlisting ====================

    /**
     * Load candidates from database with aggressive filtering.
     *
     * This stage uses indexes and constraints to avoid full table scans:
     * - Status filtering (exclude Closed)
     * - Recency window (last 90 days)
     * - Optional category match
     * - Hard maximum of 500 candidates
     *
     * Design rationale:
     * - Recency: Recent tickets have more context relevance
     * - Status: Closed tickets are historical; users want current patterns
     * - Category: Optional; allows cross-category when disabled
     * - Max: Prevents memory explosion and scoring timeout
     *
     * @param User $user Authenticated user (for authorization)
     * @param string|null $category Optional category to match
     * @return EloquentCollection Shortlisted Ticket collection
     */
    private function shortlistCandidates(User $user, ?string $category = null): EloquentCollection
    {
        $query = Ticket::query()
            // Step 1: Status filter - exclude closed/resolved tickets
            // Rationale: Users want active/current patterns, not historical archive
            ->whereNotIn('status', ['closed', 'resolved'])

            // Step 2: Recency window - last 90 days
            // Rationale: Old tickets less relevant; reduces search space
            ->where('created_at', '>=', now()->subDays(self::RECENCY_DAYS))

            // Step 3: Optional category matching
            // When disabled, suggests cross-category tickets (useful for
            // "this might be a common infrastructure issue type" patterns)
            ->when(self::REQUIRE_CATEGORY_MATCH && $category, function ($q) use ($category) {
                $q->where('category', $category);
            })

            // Step 4: Order by recent first for stable ranking
            ->orderByDesc('created_at')

            // Step 5: Hard maximum - prevent runaway queries
            ->limit(self::MAX_CANDIDATES);

        // Both employees and agents see all active tickets for similarity.
        // This is safe because results don't include sensitive fields.
        return $query->get();
    }

    // ==================== Stage 2: Text Normalization & Tokenization ====================

    /**
     * Normalize text for consistent token extraction.
     *
     * Pipeline:
     * 1. Lowercase - uniform comparison
     * 2. Remove punctuation - avoid token fragmentation
     * 3. Remove symbols - focus on alphanumeric
     * 4. Collapse whitespace - single spaces
     *
     * Rationale: Different users may write "VPN" vs "vpn", "error!" vs "error",
     * with extra spaces. Normalization ensures consistent feature extraction.
     *
     * @param string $text Raw text to normalize
     * @return string Normalized text
     */
    private function normalizeText(string $text): string
    {
        // Step 1: Lowercase
        $text = Str::lower($text);

        // Step 2: Remove punctuation but preserve internal spacing
        $text = preg_replace('/[^\w\s]/', ' ', $text) ?? '';

        // Step 3: Collapse multiple spaces to single
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        // Step 4: Trim edges
        return trim($text);
    }

    /**
     * Split normalized text into tokens (words).
     *
     * Pipeline:
     * 1. Split on whitespace
     * 2. Filter empty tokens
     * 3. Remove stop-words (common words with low signal)
     * 4. Filter extremely short tokens
     *
     * Rationale: Stops-word removal reduces noise; length filtering
     * prevents single-letter matches.
     *
     * Example:
     *   "The VPN is down" -> normalized -> "the vpn is down"
     *   -> split -> ["the", "vpn", "is", "down"]
     *   -> filter stop-words -> ["vpn", "down"]
     *   -> filter length -> ["vpn", "down"]
     *
     * @param string $normalizedText Already-normalized text
     * @return array<string> Array of meaningful tokens
     */
    private function tokenize(string $normalizedText): array
    {
        // Split on whitespace
        $tokens = explode(' ', $normalizedText);

        // Filter: remove empty, remove stop-words, enforce minimum length
        return array_filter($tokens, function (string $token) {
            // Skip empty strings
            if (empty($token)) {
                return false;
            }

            // Skip stop-words
            if (in_array($token, $this->stopwords, true)) {
                return false;
            }

            // Skip very short tokens
            if (strlen($token) < self::MIN_TOKEN_LENGTH) {
                return false;
            }

            return true;
        });
    }

    // ==================== Stage 3: Feature Extraction ====================

    /**
     * Build feature vectors (token sets and counts) from ticket fields.
     *
     * Returns both:
     * - token_set: Unique tokens (for set-based similarity)
     * - token_counts: Frequency map (for weighted overlap)
     * - combined_text: Concatenated full text (for reference)
     *
     * Rationale: Separate analysis of subject vs description allows
     * weighted scoring where subject is emphasized.
     *
     * @param string $subject Ticket subject line
     * @param string $description Ticket description body
     * @return array{
     *     subject_tokens: array<string>,
     *     description_tokens: array<string>,
     *     subject_counts: array<string, int>,
     *     description_counts: array<string, int>
     * } Feature vector
     */
    private function buildFeatures(string $subject, string $description): array
    {
        // Normalize and tokenize subject
        $subjectNorm = $this->normalizeText($subject);
        $subjectTokens = $this->tokenize($subjectNorm);
        $subjectCounts = array_count_values($subjectTokens);

        // Normalize and tokenize description
        $descriptionNorm = $this->normalizeText($description);
        $descriptionTokens = $this->tokenize($descriptionNorm);
        $descriptionCounts = array_count_values($descriptionTokens);

        return [
            'subject_tokens' => array_unique($subjectTokens),
            'description_tokens' => array_unique($descriptionTokens),
            'subject_counts' => $subjectCounts,
            'description_counts' => $descriptionCounts,
        ];
    }

    // ==================== Stage 4: Scoring ====================

    /**
     * Score a candidate ticket against the draft using weighted overlap.
     *
     * Scoring strategy:
     * - Compute token overlap separately for subject and description
     * - Apply Jaccard similarity (intersection / union)
     * - Weight subject (65%) higher than description (35%)
     * - Penalize extremely small overlaps
     *
     * Formula:
     *   overlap = |tokens_common| / |tokens_union|
     *   score = (overlap_subject * 0.65) + (overlap_description * 0.35)
     *
     * Rationale:
     * - Subject is the headline; more important
     * - Description adds context but is verbose
     * - Jaccard penalizes weak matches naturally
     *
     * Example scoring:
     *   Draft: "VPN Connection Issues"
     *   Candidate A: "VPN down" -> 2/4 overlap = 0.50 subject sim * 0.65 = 0.325
     *   Candidate B: "Password reset problems" -> 0/5 overlap = 0.0
     *   Score for A: 0.325 (included)
     *   Score for B: 0.0 (filtered out)
     *
     * @param array $draftFeatures Draft ticket features
     * @param array $candidateFeatures Candidate ticket features
     * @return float Score from 0.0 to 1.0
     */
    private function scoreCandidate(array $draftFeatures, array $candidateFeatures): float
    {
        // Compute subject similarity using token overlap
        $subjectSimilarity = $this->computeTokenOverlap(
            $draftFeatures['subject_tokens'],
            $candidateFeatures['subject_tokens']
        );

        // Compute description similarity using token overlap
        $descriptionSimilarity = $this->computeTokenOverlap(
            $draftFeatures['description_tokens'],
            $candidateFeatures['description_tokens']
        );

        // Weighted sum: subject weighted higher
        $score = (
            ($subjectSimilarity * self::WEIGHT_SUBJECT) +
            ($descriptionSimilarity * self::WEIGHT_DESCRIPTION)
        );

        // Ensure score is bounded [0.0, 1.0]
        return max(0.0, min(1.0, $score));
    }

    /**
     * Compute Jaccard-style token overlap similarity.
     *
     * Jaccard similarity = |A ∩ B| / |A ∪ B|
     *
     * Returns 0.0 if either set is empty (no signal).
     *
     * Rationale: Natural penalization of weak matches.
     * - Perfect match: 1.0
     * - 50% overlap: 0.33 to 0.66 (depends on union size)
     * - No overlap: 0.0
     *
     * @param array<string> $tokenSet1 First token set
     * @param array<string> $tokenSet2 Second token set
     * @return float Similarity score [0.0, 1.0]
     */
    private function computeTokenOverlap(array $tokenSet1, array $tokenSet2): float
    {
        // Handle empty sets
        if (empty($tokenSet1) || empty($tokenSet2)) {
            return 0.0;
        }

        // Compute intersection and union
        $intersection = array_intersect($tokenSet1, $tokenSet2);
        $union = array_unique(array_merge($tokenSet1, $tokenSet2));

        // Compute Jaccard similarity
        if (empty($union)) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }

    // ==================== Stage 5: Output Shaping ====================

    /**
     * Shape results for safe client consumption.
     *
     * Returns only:
     * - id: For linking/fetching full ticket
     * - subject: Display title
     * - description_snippet: First 150 chars (preview)
     * - category: For user context
     * - status: Current ticket status
     * - created_at: When it was created
     * - relevance_score: Why it was matched (0.0-1.0)
     *
     * Does NOT include:
     * - Full description (too much data)
     * - Internal metadata
     * - Agent assignments or private notes
     * - Sensitive system fields
     *
     * Rationale:
     * - Minimal payload for performance
     * - Safe to display to any user
     * - Score builds trust ("see why we matched this")
     *
     * @param Collection $scored Scored results with tickets and scores
     * @return array<array> Safe JSON-serializable array
     */
    private function shapeResults(Collection $scored): array
    {
        return $scored->map(function (array $item) {
            $ticket = $item['ticket'];
            $score = $item['score'];

            // Generate description snippet: first 150 chars + "..."
            $snippet = Str::limit($ticket->description, 150, '...');

            return [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'description_snippet' => $snippet,
                'category' => $ticket->category->value,
                'status' => $ticket->status->value,
                'created_at' => $ticket->created_at,
                'relevance_score' => round($score, 2),
            ];
        })->toArray();
    }

    // ==================== Future Lane 2 Extension Point ====================

    /**
     * Future embedding-based similarity (Lane 2):
     *
     * To implement semantic similarity using embeddings in the future:
     *
     * 1. EMBEDDING GENERATION (one-time, off-line):
     *    - When a ticket is created, generate embedding via OpenAI/similar
     *    - Store in new `ticket_embeddings` table (ticket_id, embedding VECTOR)
     *    - Index with pgvector or similar
     *
     * 2. REPLACE shortlistCandidates():
     *    - Keep the same filtering (recency, status, category)
     *    - Add vector similarity query: `... ORDER BY embedding <-> draft_embedding LIMIT 50`
     *    - Dramatically improves candidate quality
     *
     * 3. REPLACE scoreCandidate():
     *    - Compute cosine distance between embeddings
     *    - Can remove tokenization/normalization entirely
     *    - Returns scalar similarity (0.0 to 1.0)
     *
     * 4. KEEP Stage 6 (shapeResults()):
     *    - Output format stays identical
     *    - Clients don't need to change
     *
     * Implementation path:
     *   a) Add migration: create ticket_embeddings table
     *   b) Add EmbeddingService: handles OpenAI calls + caching
     *   c) Create new SimilarTicketEngine method: embeddingBased()
     *   d) Update controller to switch between engines via config
     *   e) Add tests comparing Lane 1 vs Lane 2 results
     *
     * Why Lane 1 works today:
     * - Deterministic and explainable
     * - No external API calls
     * - No model training or hallucinations
     * - Perfectly fine for keyword-heavy issues (VPN, password, etc.)
     *
     * Why Lane 2 would be better:
     * - Semantic understanding ("VPN down" ~= "can't connect")
     * - Reduced stop-word dependency
     * - Captures intent beyond token overlap
     * - Better for complex multi-sentence descriptions
     */
}
