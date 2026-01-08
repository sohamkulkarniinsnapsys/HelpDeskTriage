<?php

use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->agent = User::factory()->create(['role' => Role::Agent]);
    $this->employee = User::factory()->create(['role' => Role::Employee]);
});

test('employee can create a ticket', function () {
    $ticketData = [
        'subject' => 'Test ticket subject',
        'description' => 'This is a test ticket description',
        'category' => TicketCategory::Hardware->value,
        'severity' => 3,
    ];

    $response = $this->actingAs($this->employee)
        ->postJson(route('tickets.store'), $ticketData);

    $response->assertCreated()
        ->assertJsonFragment(['subject' => 'Test ticket subject']);

    $this->assertDatabaseHas('tickets', [
        'subject' => 'Test ticket subject',
        'created_by' => $this->employee->id,
        'status' => TicketStatus::Open->value,
    ]);
});

test('agent can create a ticket', function () {
    $ticketData = [
        'subject' => 'Agent created ticket',
        'description' => 'Description',
        'category' => TicketCategory::Bug->value,
        'severity' => 5,
    ];

    $response = $this->actingAs($this->agent)
        ->postJson(route('tickets.store'), $ticketData);

    $response->assertCreated();
});

test('employee can view their own tickets', function () {
    $ticket = Ticket::factory()->create(['created_by' => $this->employee->id]);

    $response = $this->actingAs($this->employee)
        ->getJson(route('tickets.show', $ticket));

    $response->assertOk()
        ->assertJsonFragment(['id' => $ticket->id]);
});

test('employee cannot view other employees tickets', function () {
    $otherEmployee = User::factory()->create(['role' => Role::Employee]);
    $ticket = Ticket::factory()->create(['created_by' => $otherEmployee->id]);

    $response = $this->actingAs($this->employee)
        ->getJson(route('tickets.show', $ticket));

    $response->assertForbidden();
});

test('agent can view all tickets', function () {
    $employeeTicket = Ticket::factory()->create([
        'created_by' => $this->employee->id,
    ]);

    $response = $this->actingAs($this->agent)
        ->getJson(route('tickets.show', $employeeTicket));

    $response->assertOk();
});

test('ticket list is paginated', function () {
    Ticket::factory()->count(20)->create(['created_by' => $this->employee->id]);

    $response = $this->actingAs($this->employee)
        ->getJson(route('tickets.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'current_page',
            'data',
            'first_page_url',
            'from',
            'last_page',
            'last_page_url',
            'links',
            'next_page_url',
            'path',
            'per_page',
            'prev_page_url',
            'to',
            'total',
        ]);

    expect($response->json('total'))->toBe(20)
        ->and($response->json('per_page'))->toBe(15)
        ->and($response->json('current_page'))->toBe(1);
});

test('tickets can be filtered by status', function () {
    Ticket::factory()->create([
        'created_by' => $this->employee->id,
        'status' => TicketStatus::Open,
    ]);
    
    Ticket::factory()->create([
        'created_by' => $this->employee->id,
        'status' => TicketStatus::Closed,
    ]);

    $response = $this->actingAs($this->employee)
        ->getJson(route('tickets.index', ['status' => TicketStatus::Open->value]));

    $response->assertOk();
    
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['status'])->toBe(TicketStatus::Open->value);
});

test('tickets can be filtered by category', function () {
    Ticket::factory()->create([
        'created_by' => $this->employee->id,
        'category' => TicketCategory::Hardware,
    ]);
    
    Ticket::factory()->create([
        'created_by' => $this->employee->id,
        'category' => TicketCategory::Network,
    ]);

    $response = $this->actingAs($this->employee)
        ->getJson(route('tickets.index', ['category' => TicketCategory::Hardware->value]));

    $response->assertOk();
    
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['category'])->toBe(TicketCategory::Hardware->value);
});

test('tickets can be searched by subject', function () {
    Ticket::factory()->create([
        'created_by' => $this->employee->id,
        'subject' => 'Cannot access VPN',
    ]);
    
    Ticket::factory()->create([
        'created_by' => $this->employee->id,
        'subject' => 'Printer not working',
    ]);

    $response = $this->actingAs($this->employee)
        ->getJson(route('tickets.index', ['search' => 'VPN']));

    $response->assertOk();
    
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['subject'])->toContain('VPN');
});

test('agent can filter unassigned tickets', function () {
    Ticket::factory()->create(['assigned_to' => null]);
    Ticket::factory()->create(['assigned_to' => $this->agent->id]);

    $response = $this->actingAs($this->agent)
        ->getJson(route('tickets.index', ['unassigned' => true]));

    $response->assertOk();
    
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['assigned_to'])->toBeNull();
});

test('agent can update ticket', function () {
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($this->agent)
        ->patchJson(route('tickets.update', $ticket), [
            'subject' => 'Updated subject',
            'severity' => 5,
        ]);

    $response->assertOk()
        ->assertJsonFragment(['subject' => 'Updated subject']);
});

test('employee cannot update tickets', function () {
    $ticket = Ticket::factory()->create(['created_by' => $this->employee->id]);

    $response = $this->actingAs($this->employee)
        ->patchJson(route('tickets.update', $ticket), [
            'subject' => 'Updated subject',
        ]);

    $response->assertForbidden();
});

test('agent can assign ticket to themselves', function () {
    $ticket = Ticket::factory()->create(['assigned_to' => null]);

    $response = $this->actingAs($this->agent)
        ->patchJson(route('tickets.assign', $ticket), [
            'assigned_to' => $this->agent->id,
        ]);

    $response->assertOk()
        ->assertJsonFragment(['assigned_to' => $this->agent->id]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
});

test('agent can unassign ticket', function () {
    $ticket = Ticket::factory()->create(['assigned_to' => $this->agent->id]);

    $response = $this->actingAs($this->agent)
        ->patchJson(route('tickets.assign', $ticket), [
            'assigned_to' => null,
        ]);

    $response->assertOk()
        ->assertJsonFragment(['assigned_to' => null]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

test('agent can update ticket status', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    $response = $this->actingAs($this->agent)
        ->patchJson(route('tickets.update-status', $ticket), [
            'status' => TicketStatus::Resolved->value,
        ]);

    $response->assertOk();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);
});

test('employee cannot update ticket status', function () {
    $ticket = Ticket::factory()->create(['created_by' => $this->employee->id]);

    $response = $this->actingAs($this->employee)
        ->patchJson(route('tickets.update-status', $ticket), [
            'status' => TicketStatus::Resolved->value,
        ]);

    $response->assertForbidden();
});

test('agent can delete tickets', function () {
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($this->agent)
        ->deleteJson(route('tickets.destroy', $ticket));

    $response->assertNoContent();
    $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
});

test('employee cannot delete tickets', function () {
    $ticket = Ticket::factory()->create(['created_by' => $this->employee->id]);

    $response = $this->actingAs($this->employee)
        ->deleteJson(route('tickets.destroy', $ticket));

    $response->assertForbidden();
});

test('statistics endpoint returns correct counts', function () {
    Ticket::factory()->count(3)->create([
        'created_by' => $this->employee->id,
        'status' => TicketStatus::Open,
    ]);
    
    Ticket::factory()->count(2)->create([
        'created_by' => $this->employee->id,
        'status' => TicketStatus::Closed,
    ]);

    $response = $this->actingAs($this->employee)
        ->getJson(route('tickets.statistics'));

    $response->assertOk()
        ->assertJson([
            'total' => 5,
            'open' => 3,
            'closed' => 2,
        ]);
});
