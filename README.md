## HelpDesk Triage (Laravel 12)

HelpDesk Triage is a focused Laravel 12 application for internal IT ticketing and triage. It supports employee ticket creation, agent ticket management, secure attachment handling, and an explainable Lane 1 similarity engine that suggests related tickets while a user drafts a new one.

### Why this exists
- Make it easy for employees to file tickets with the right metadata.
- Give agents complete visibility and safe controls to assign, update, and close tickets.
- Reduce duplicate tickets with transparent, deterministic similarity suggestions.

### Feature overview
- Role-based access: Employees vs Agents with policy-enforced visibility and actions.
- Ticket lifecycle: create, assign, update status, paginate, filter, and search.
- Secure attachments: private storage, UUID filenames, strict policy checks on download/delete.
- Similarity engine (Lane 1): deterministic text normalization, scoring, and top-5 ranked suggestions.
- Seeded demo data: sample users (agents/employees) and tickets for fast evaluation.

### Web UI usage
- Auth UI (Laravel Breeze): visit `/login` to sign in with seeded credentials, `/register` for new accounts, `/forgot-password` for recovery.
- Dashboard: `/dashboard` redirects employees to their list, agents to all tickets.
- Employees: create via `/tickets/create`; browse `/tickets/my` with filters (status, category, severity, search, pagination); detail at `/tickets/{id}/detail` with attachments download.
- Agents: browse `/tickets/all` (plus unassigned filter); detail page includes assignment and status update forms; can download attachments for any ticket.
- Similarity: ticket create form auto-checks similar tickets while typing, with a manual “Check Similar Tickets” trigger.
- Attachments: added during create; downloads served through protected routes.

## Architecture & Domain Model
- Core models: `User`, `Ticket`, `Attachment` with enums for `Role`, `TicketStatus`, `TicketCategory`.
- Authorization: Policies guard every controller action; scopes (e.g., `visibleTo`) enforce visibility in queries.
- Services: `TicketService` for ticket workflows; `AttachmentService` for storage and metadata; `TicketSimilarityEngine` for Lane 1 similarity.
- HTTP layer: REST-style controllers for tickets, attachments, and similarity queries; routes grouped by auth/role middleware.

## Roles & Authorization
- Employees: create tickets; view/manage only their own tickets; manage their own attachments.
- Agents: view/manage all tickets; can assign, update status, and delete tickets; manage all attachments.
- Enforcement: Policies (`TicketPolicy`, `AttachmentPolicy`) and gates are checked in controllers and scopes. Visibility is never delegated to views.

## Ticket Workflows
- Create: employees/agents create tickets; defaults to `open` status.
- Assign/unassign: agent-only; assigning moves status to `in_progress`, unassigning reopens to `open`.
- Status updates: agent-only; status enum guards valid transitions.
- Listing: filter by status, category, search term, and unassigned; results are paginated and scoped by role.

## Attachment Security
- Storage: private `local` disk under `attachments/tickets/{ticket_id}/{uuid.ext}`; filenames are UUID-based and not guessable.
- Access: downloads/deletes authorized via `AttachmentPolicy` (employees only on their tickets; agents on all).
- Delivery: downloads stream from the private path with original filename and MIME type headers; no public URLs are exposed.

## Similarity Detection (Lane 1)
Deterministic, explainable keyword overlap designed for interactive use.

**Pipeline**
- Shortlist: last 90 days, excludes `closed`/`resolved`, up to 500 candidates, no category constraint (cross-category allowed). For similarity only, both roles consider all active tickets; only safe fields are returned.
- Normalization: lowercase, remove punctuation/symbols, collapse whitespace, trim.
- Tokenization: whitespace split; stop-words removed; tokens shorter than 2 characters dropped.
- Feature building: unique token sets + token counts for subject and description.
- Scoring: Jaccard overlap per field; weighted subject 0.65, description 0.35; bounded 0..1.
- Ranking: filter relevance >= 0.05; sort desc; return top 5 with rounded scores.

**What it captures well**
- Keyword-heavy helpdesk reports (VPN, password reset, access requests).
- Cases where subject similarity should dominate description noise.
- Recency-focused matching to surface current patterns.

**Intentional limitations**
- Semantic equivalence beyond token overlap (e.g., synonyms) is not detected.
- Extremely short drafts may score low; threshold kept permissive (0.05).
- English-focused stop-words; multilingual input not optimized.

**Lane 2 (future embedding path)**
- Add `ticket_embeddings` table and background embedding generation.
- Replace shortlist ordering with vector similarity (pgvector or similar).
- Replace scoring with cosine similarity; keep the same response shape for clients.

## Environment Requirements
- PHP 8.2+
- Composer
- Node.js 20+ and npm
- SQLite (default) or alternative DB via `.env`
- Local PHP path (Herd on Windows): `C:\Users\soham\.config\herd\bin\php84\php.exe`

## Local Setup
1. Clone and install dependencies:
	- `composer install`
	- `npm install`
2. Environment: copy `.env.example` to `.env` and adjust DB/mail settings.
3. Keys & schema:
	- `php artisan key:generate`
	- `php artisan migrate --seed`
4. Build assets: `npm run build` (or `npm run dev` with Vite).
5. Run app (dev): `composer run dev` (serves Laravel, queue listener, Vite).

## Demo Data & Credentials
Seeded via `php artisan migrate --seed`:
- Agents: `sarah.martinez@company.test` / `password`, `james.chen@company.test`, `aisha.patel@company.test`.
- Employees: `michael.brown@company.test` / `password`, `emily.johnson@company.test`, `david.kim@company.test`, `jessica.williams@company.test`, `robert.garcia@company.test`.

## Testing & Quality
- Run tests: `composer run test` (clears config then runs Pest).
- Code style: `./vendor/bin/pint`.
- Static analysis (optional): install Larastan (`composer require --dev nunomaduro/larastan`) then `./vendor/bin/phpstan analyse`.

## Known Trade-offs & Considerations
- Similarity is token-based; semantic matches require future Lane 2 embeddings.
- Recency window (90 days) may hide older but relevant tickets; adjust `RECENCY_DAYS` as needed.
- Attachment storage uses local disk; for cloud storage ensure private buckets and keep policy checks.
- Status transitions are intentionally simple; workflows with approvals/escalations would need richer state machines.

## Project Structure Highlights
- Domain logic: [app/Models](app/Models), [app/Enums](app/Enums).
- Authorization: [app/Policies/TicketPolicy.php](app/Policies/TicketPolicy.php) and [app/Policies/AttachmentPolicy.php](app/Policies/AttachmentPolicy.php).
- Services: [app/Services/TicketService.php](app/Services/TicketService.php), [app/Services/AttachmentService.php](app/Services/AttachmentService.php), [app/Services/TicketSimilarityEngine.php](app/Services/TicketSimilarityEngine.php).
- HTTP layer: controllers under [app/Http/Controllers](app/Http/Controllers), routes in [routes/web.php](routes/web.php).

## Running the App (quick commands)
- Dev servers: `composer run dev` (Laravel, queue listener, Vite with HMR).
- Tests: `composer run test`.
- Lint: `./vendor/bin/pint`.

## Performance Notes
- Similarity shortlist capped at 500 candidates; scoring is in-memory and fast for interactive use.
- Attachments stream from disk; ensure disk IO is acceptable for your environment or move to managed storage.

## Handover Notes
- Tests are green (Pest) with seeded data.
- Policies guard every controller entry point; there are no view-only authorization checks.
- Similarity results are intentionally non-sensitive and limited to id, subject, snippet, category, status, created_at, relevance_score.
