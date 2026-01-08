<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Policies\AttachmentPolicy;
use App\Policies\TicketPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Ticket::class => TicketPolicy::class,
        Attachment::class => AttachmentPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerGates();
    }

    /**
     * Register the application's policies.
     */
    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Register custom authorization gates.
     */
    protected function registerGates(): void
    {
        // Gate for checking if user is an agent
        Gate::define('act-as-agent', function ($user) {
            return $user->role->isAgent();
        });

        // Gate for checking if user is an employee
        Gate::define('act-as-employee', function ($user) {
            return $user->role->isEmployee();
        });

        // Gate for viewing tickets scoped to the user's role
        Gate::define('view-ticket-list', function ($user) {
            return true; // Both roles can view list, but filtering applies
        });
    }
}
