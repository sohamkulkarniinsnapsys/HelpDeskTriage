<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = User::factory()->create(['role' => Role::Employee]);
    $this->agent = User::factory()->create(['role' => Role::Agent]);
});

describe('SimilarTicketController', function () {
    test('requires authentication', function () {
        $response = $this->postJson(route('tickets.similar'), [
            'subject' => 'VPN Issue',
            'description' => 'Cannot connect to VPN',
        ]);

        $response->assertUnauthorized();
    });

    test('validates subject is required', function () {
        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'description' => 'Cannot connect to VPN',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('subject');
    });

    test('validates description is required', function () {
        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Issue',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    });

    test('validates subject minimum length', function () {
        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'ab',
                'description' => 'This is a description',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('subject');
    });

    test('validates description minimum length', function () {
        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'Test Subject',
                'description' => 'short',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    });

    test('accepts valid request', function () {
        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Connection Issues',
                'description' => 'Users cannot connect to VPN from remote locations',
            ]);

        $response->assertOk()
            ->assertJson([]);
    });

    test('returns similar tickets', function () {
        Ticket::factory()->create([
            'subject' => 'VPN Connection Failed',
            'description' => 'Cannot connect to VPN',
            'created_by' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Connection Issues',
                'description' => 'Users unable to connect to VPN server',
            ]);

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonStructure([
                0 => ['id', 'subject', 'description_snippet', 'category', 'status', 'created_at', 'relevance_score']
            ]);
    });

    test('respects employee authorization', function () {
        $otherEmployee = User::factory()->create(['role' => Role::Employee]);
        
        Ticket::factory()->create([
            'subject' => 'VPN Issue',
            'description' => 'Cannot connect to VPN',
            'created_by' => $otherEmployee->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Problem',
                'description' => 'Cannot connect to VPN',
            ]);

        // Employee can see tickets by other employees
        $response->assertOk()
            ->assertJsonCount(1);
    });

    test('agent can see all tickets', function () {
        $employee1 = User::factory()->create(['role' => Role::Employee]);
        $employee2 = User::factory()->create(['role' => Role::Employee]);

        Ticket::factory()->create([
            'subject' => 'VPN Issue',
            'description' => 'Cannot connect to VPN',
            'created_by' => $employee1->id,
        ]);

        Ticket::factory()->create([
            'subject' => 'VPN Down',
            'description' => 'Cannot connect to VPN',
            'created_by' => $employee2->id,
        ]);

        $response = $this->actingAs($this->agent)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Problem',
                'description' => 'Cannot connect to VPN',
            ]);

        $response->assertOk()
            ->assertJsonCount(2);
    });

    test('accepts optional category parameter', function () {
        Ticket::factory()->create([
            'subject' => 'VPN Issue',
            'description' => 'Cannot connect to VPN',
            'category' => 'network',
            'created_by' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Problem',
                'description' => 'Cannot connect to VPN',
                'category' => 'network',
            ]);

        $response->assertOk();
    });

    test('returns empty array when no similar tickets found', function () {
        Ticket::factory()->create([
            'subject' => 'Office Supplies Request',
            'description' => 'Need more paper and pens',
            'created_by' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Connection Issues',
                'description' => 'Cannot connect to VPN from remote',
            ]);

        $response->assertOk()
            ->assertJson([]);
    });

    test('returns top 5 results when more than 5 matches', function () {
        for ($i = 0; $i < 10; $i++) {
            Ticket::factory()->create([
                'subject' => "VPN Issue #$i",
                'description' => 'Cannot connect to VPN',
                'created_by' => $this->employee->id,
            ]);
        }

        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Connection Problems',
                'description' => 'Cannot connect to VPN',
            ]);

        $response->assertOk()
            ->assertJsonCount(5);
    });

    test('returns tickets sorted by relevance score', function () {
        Ticket::factory()->create([
            'subject' => 'VPN Connection Failed',
            'description' => 'Cannot connect',
            'created_by' => $this->employee->id,
        ]);

        Ticket::factory()->create([
            'subject' => 'Email Setup',
            'description' => 'VPN configuration needed for email',
            'created_by' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Issues',
                'description' => 'Cannot connect to VPN',
            ]);

        $response->assertOk()
            ->assertJsonCount(2);

        $tickets = $response->json();
        expect($tickets[0]['relevance_score'])->toBeGreaterThanOrEqual($tickets[1]['relevance_score']);
    });

    test('response includes correct fields', function () {
        $ticket = Ticket::factory()->create([
            'subject' => 'VPN Issue',
            'description' => 'Cannot connect to VPN from remote location',
            'category' => 'network',
            'status' => TicketStatus::Open,
            'created_by' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Problem',
                'description' => 'Cannot connect',
            ]);

        $response->assertOk()
            ->assertJsonCount(1);

        $result = $response->json(0);
        expect($result)->toHaveKeys(['id', 'subject', 'description_snippet', 'category', 'status', 'created_at', 'relevance_score'])
            ->and($result['id'])->toBe($ticket->id)
            ->and($result['subject'])->toBe('VPN Issue')
            ->and($result['category'])->toBe('network')
            ->and($result['status'])->toBe('open');
    });

    test('response does not include sensitive fields', function () {
        Ticket::factory()->create([
            'subject' => 'VPN Issue',
            'description' => 'This is a full description that should not be exposed',
            'created_by' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson(route('tickets.similar'), [
                'subject' => 'VPN Problem',
                'description' => 'Cannot connect',
            ]);

        $response->assertOk();
        $result = $response->json(0);

        // Should NOT include full description
        expect($result)->not->toHaveKey('description')
            ->and(strlen($result['description_snippet']))->toBeLessThanOrEqual(153);
    });
});
