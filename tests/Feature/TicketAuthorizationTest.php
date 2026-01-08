<?php

use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test users
    $this->agent = User::factory()->create(['role' => Role::Agent]);
    $this->employee = User::factory()->create(['role' => Role::Employee]);
    $this->otherEmployee = User::factory()->create(['role' => Role::Employee]);

    // Create test tickets
    $this->employeeTicket = Ticket::factory()->create([
        'created_by' => $this->employee->id,
        'status' => TicketStatus::Open,
    ]);

    $this->otherTicket = Ticket::factory()->create([
        'created_by' => $this->otherEmployee->id,
        'status' => TicketStatus::Open,
    ]);

    $this->assignedTicket = Ticket::factory()->create([
        'created_by' => $this->employee->id,
        'assigned_to' => $this->agent->id,
        'status' => TicketStatus::InProgress,
    ]);
});

test('agent can view all tickets', function () {
    expect($this->agent->can('view', $this->employeeTicket))->toBeTrue()
        ->and($this->agent->can('view', $this->otherTicket))->toBeTrue()
        ->and($this->agent->can('view', $this->assignedTicket))->toBeTrue();
});

test('employee can view their own tickets', function () {
    expect($this->employee->can('view', $this->employeeTicket))->toBeTrue()
        ->and($this->employee->can('view', $this->assignedTicket))->toBeTrue();
});

test('employee cannot view other employees tickets', function () {
    expect($this->employee->can('view', $this->otherTicket))->toBeFalse();
});

test('both agents and employees can create tickets', function () {
    expect($this->agent->can('create', Ticket::class))->toBeTrue()
        ->and($this->employee->can('create', Ticket::class))->toBeTrue();
});

test('only agents can update tickets', function () {
    expect($this->agent->can('update', $this->employeeTicket))->toBeTrue()
        ->and($this->employee->can('update', $this->employeeTicket))->toBeFalse();
});

test('only agents can delete tickets', function () {
    expect($this->agent->can('delete', $this->employeeTicket))->toBeTrue()
        ->and($this->employee->can('delete', $this->employeeTicket))->toBeFalse();
});

test('only agents can assign tickets', function () {
    expect($this->agent->can('assign', $this->employeeTicket))->toBeTrue()
        ->and($this->employee->can('assign', $this->employeeTicket))->toBeFalse();
});

test('only agents can update ticket status', function () {
    expect($this->agent->can('updateStatus', $this->employeeTicket))->toBeTrue()
        ->and($this->employee->can('updateStatus', $this->employeeTicket))->toBeFalse();
});

test('visible to scope filters tickets correctly for employees', function () {
    $tickets = Ticket::visibleTo($this->employee)->get();

    expect($tickets)->toHaveCount(2)
        ->and($tickets->pluck('id')->toArray())->toContain($this->employeeTicket->id)
        ->and($tickets->pluck('id')->toArray())->toContain($this->assignedTicket->id)
        ->and($tickets->pluck('id')->toArray())->not->toContain($this->otherTicket->id);
});

test('visible to scope returns all tickets for agents', function () {
    $tickets = Ticket::visibleTo($this->agent)->get();

    expect($tickets)->toHaveCount(3);
});

test('gate act as agent works correctly', function () {
    expect($this->agent->can('act-as-agent'))->toBeTrue()
        ->and($this->employee->can('act-as-agent'))->toBeFalse();
});

test('gate act as employee works correctly', function () {
    expect($this->employee->can('act-as-employee'))->toBeTrue()
        ->and($this->agent->can('act-as-employee'))->toBeFalse();
});
