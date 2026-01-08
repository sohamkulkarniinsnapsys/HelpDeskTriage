<?php

use App\Enums\Role;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test users
    $this->agent = User::factory()->create(['role' => Role::Agent]);
    $this->employee = User::factory()->create(['role' => Role::Employee]);
    $this->otherEmployee = User::factory()->create(['role' => Role::Employee]);

    // Create test tickets and attachments
    $this->employeeTicket = Ticket::factory()->create([
        'created_by' => $this->employee->id,
    ]);

    $this->otherTicket = Ticket::factory()->create([
        'created_by' => $this->otherEmployee->id,
    ]);

    $this->employeeAttachment = Attachment::factory()->create([
        'ticket_id' => $this->employeeTicket->id,
    ]);

    $this->otherAttachment = Attachment::factory()->create([
        'ticket_id' => $this->otherTicket->id,
    ]);
});

test('agent can view all attachments', function () {
    expect($this->agent->can('view', $this->employeeAttachment))->toBeTrue()
        ->and($this->agent->can('view', $this->otherAttachment))->toBeTrue();
});

test('employee can view attachments on their own tickets', function () {
    expect($this->employee->can('view', $this->employeeAttachment))->toBeTrue();
});

test('employee cannot view attachments on other tickets', function () {
    expect($this->employee->can('view', $this->otherAttachment))->toBeFalse();
});

test('only agents can update attachments', function () {
    expect($this->agent->can('update', $this->employeeAttachment))->toBeTrue()
        ->and($this->employee->can('update', $this->employeeAttachment))->toBeFalse();
});

test('agent can delete any attachment', function () {
    expect($this->agent->can('delete', $this->employeeAttachment))->toBeTrue()
        ->and($this->agent->can('delete', $this->otherAttachment))->toBeTrue();
});

test('employee can delete attachments on their own tickets', function () {
    expect($this->employee->can('delete', $this->employeeAttachment))->toBeTrue();
});

test('employee cannot delete attachments on other tickets', function () {
    expect($this->employee->can('delete', $this->otherAttachment))->toBeFalse();
});

test('both agents and employees can create attachments', function () {
    expect($this->agent->can('create', Attachment::class))->toBeTrue()
        ->and($this->employee->can('create', Attachment::class))->toBeTrue();
});
