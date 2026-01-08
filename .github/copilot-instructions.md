# AI Copilot Instructions for helpdesktriage

This Laravel 12 application provides helpdesk ticketing/triage functionality. Below is essential knowledge for AI agents working on this codebase.

## Project Overview

- **Framework**: Laravel 12 with PHP ^8.2
- **Frontend**: Vite + Tailwind CSS 4.0
- **Testing**: Pest 4.3 with Laravel plugin
- **Key Tools**: Artisan CLI, Composer, npm
- **PHP Environment**: Herd (Windows) - PHP 8.4 located at `C:\Users\soham\.config\herd\bin\php84\php.exe`

## Architecture & Key Components

### Directory Structure
- `app/` - Application logic (Models, Controllers, Providers)
  - `app/Enums/` - PHP enums (Role, TicketStatus, TicketCategory)
  - `app/Policies/` - Authorization policies (TicketPolicy, AttachmentPolicy)
  - `app/Http/Middleware/` - Custom middleware (EnsureUserIsAgent, EnsureUserIsEmployee)
- `routes/` - Route definitions (`web.php`, `console.php`)
- `resources/` - Frontend assets (CSS, JS) and Blade templates
- `config/` - Configuration files (database, app settings, etc.)
- `database/` - Migrations, seeders, and model factories
- `tests/` - Unit and Feature tests (using Pest)

### Domain Model
**Roles**: Employee (creates tickets) and Agent (manages all tickets)
**Core Entities**:
- `User`: Has role (employee/agent), creates tickets, can be assigned tickets
- `Ticket`: Subject, description, category, severity (1-5), status, creator, assignee
- `Attachment`: Belongs to ticket, stores file metadata

### Authorization System
**CRITICAL**: All data access MUST be authorized. See `AUTHORIZATION.md` for complete details.
- **Policies**: `TicketPolicy` and `AttachmentPolicy` define role-based rules
- **Middleware**: `agent` and `employee` middleware for route protection
- **Gates**: `act-as-agent`, `act-as-employee` for role checks
- **Scopes**: `Ticket::visibleTo($user)` filters tickets by role (agents see all, employees see only theirs)

**Key Rules**:
- Employees can only view/manage tickets they created
- Agents can view/manage all tickets
- Only agents can assign tickets or change status
- Always use `Gate::authorize()` in controllers
- Always use `visibleTo()` scope for ticket queries

### Service Container & Dependency Injection
All services are registered through `AppServiceProvider` in `app/Providers/AppServiceProvider.php`. Use the container for dependency management rather than direct instantiation.

### Database
- Connection: SQLite for testing (`:memory:`), configurable for production in `config/database.php`
- Migrations: Located in `database/migrations/` - run migrations before feature development
- Models: Use Eloquent ORM with type hints (e.g., `User` model in `app/Models/`)

## Development Workflows

### Setup
```bash
composer run setup  # One-time initialization
```

### Local Development
```bash
composer run dev  # Runs concurrent: Laravel server, queue listener, Vite dev server
```
Uses `concurrently` to manage three processes. Watch terminal for build issues.
- **Server**: Runs on default Laravel port (typically `http://127.0.0.1:8000`)
- **Queue**: Processes jobs with `--tries=1` flag
- **Vite**: Hot module replacement for frontend assets

### Testing
```bash
composer run test  # Pest test suite (includes feature & unit tests)
```
Tests are located in `tests/Feature/` and `tests/Unit/`. Use Pest assertions and helpers.

### Code Quality
```bash
./vendor/bin/pint  # Laravel Pint for code formatting
```
PHP 8.2 type hints are expected. Use strict types.

### Artisan Commands
```bash
php artisan migrate           # Run pending migrations
php artisan tinker           # Interactive shell for testing code
php artisan queue:listen     # Process queued jobs
```

## Conventions & Patterns

### Naming
- **Models**: Singular nouns (`User`, `Ticket`) in `app/Models/`
- **Controllers**: Suffix with `Controller` in `app/Http/Controllers/`
- **Routes**: Use RESTful conventions (resource routing when applicable)
- **Migrations**: Format `YYYY_MM_DD_HHMMSS_action_name.php`

### Blade Templates
- Located in `resources/views/` with `.blade.php` extension
- Use `{{ }}` for output, `{!! !!}` for unescaped HTML
- Extend layouts and use components for reusable UI

### Testing
- **Feature tests**: Test full workflows (routes, database, etc.) in `tests/Feature/`
- **Unit tests**: Test isolated logic (helpers, services) in `tests/Unit/`
- **Pest syntax**: Use fluent assertions - `test('name')->tap()->expects()`
- **Database**: In-memory SQLite (`:memory:`) configured in `phpunit.xml`
- **RefreshDatabase**: Commented out by default in `tests/Pest.php` - uncomment when using database in tests
- **Example**: See `tests/Feature/ExampleTest.php` for basic HTTP test pattern

### Frontend
- **CSS**: `resources/css/app.css` (Tailwind 4.0)
  - Uses new `@source` directive to scan Blade templates and framework views
  - Custom theme variables defined with `@theme` block
- **JS**: `resources/js/app.js` (ES modules, imports `bootstrap.js` for Axios setup)
- **Vite**: Asset compilation via `npm run dev` (development), `npm run build` (production)
  - Auto-reloads views on change via `refresh: true` in `vite.config.js`
  - Ignores `storage/framework/views/**` to prevent watch loops

## Critical Files Reference

- **Configuration**: `config/app.php`, `config/database.php`
- **Routes**: `routes/web.php`
- **Service Provider**: `app/Providers/AppServiceProvider.php`
- **Test Config**: `phpunit.xml`
- **Frontend Build**: `vite.config.js`

## Common Tasks

**Adding a new feature**:
1. Create migration: `php artisan make:migration create_table_name`
2. Create model: `php artisan make:model ModelName -m` (with migration)
3. Define route in `routes/web.php`
4. Create controller: `php artisan make:controller ControllerName`
5. Write tests in `tests/Feature/` before implementation
6. Build views in `resources/views/`

**Debugging**:
- Use `php artisan tinker` for interactive testing
- Add `dd()` or `dump()` in code for inspection
- Check logs in `storage/logs/`
- Use Laravel Pail if enabled: `php artisan pail`

## Queue & Background Jobs

- Queue connection set to `sync` in testing (immediate execution)
- For background jobs in production, jobs are defined in `app/Jobs/` and dispatched via queue
- Use `php artisan queue:listen` in development

## Environment Variables

Copy `.env.example` to `.env` and configure:
- `APP_NAME`, `APP_ENV`, `APP_DEBUG`
- Database credentials in `DB_*`
- Mail settings in `MAIL_*`

Initial setup with `composer run setup` handles key generation and migration.

## Herd-Specific Configuration

This project uses **Herd** for local PHP development on Windows. PHP is NOT in the system PATH.

**VS Code Configuration**: `.vscode/settings.json` is configured with:
```json
{
  "php.executablePath": "C:\\Users\\soham\\.config\\herd\\bin\\php84\\php.exe"
}
```

**Running PHP Commands**: Use the full path or run commands through Herd:
- Direct: `C:\Users\soham\.config\herd\bin\php84\php.exe artisan migrate`
- Via artisan script: `php artisan migrate` (works if Herd is running)
- Composer scripts work automatically: `composer run dev`, `composer run test`
