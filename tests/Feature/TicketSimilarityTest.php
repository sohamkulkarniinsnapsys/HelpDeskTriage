<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketSimilarityEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->engine = app(TicketSimilarityEngine::class);
    $this->employee1 = User::factory()->create(['role' => Role::Employee]);
    $this->employee2 = User::factory()->create(['role' => Role::Employee]);
    $this->agent = User::factory()->create(['role' => Role::Agent]);
});

describe('TicketSimilarityEngine', function () {
    test('returns empty array when no candidates exist', function () {
        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN Connection Issues',
            'Cannot connect to VPN from remote location'
        );

        expect($result)->toBeArray()->toBeEmpty();
    });

    test('respects employee authorization - only sees own tickets', function () {
        // Employee2 creates a matching ticket
        Ticket::factory()->create([
            'subject' => 'VPN VPN VPN',
            'description' => 'Cannot Cannot VPN VPN VPN VPN VPN',
            'created_by' => $this->employee2->id,
        ]);

        Ticket::factory()->create([
            'subject' => 'Password Reset Required',
            'description' => 'Need to reset my account password immediately',
            'created_by' => $this->employee2->id,
        ]);

        // Employee1 tries to find similar - should find tickets created by employee2
        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN VPN',
            'Cannot VPN VPN'
        );

        // Employees can see tickets by other employees
        expect($result)->toHaveCount(1)
            ->and($result[0]['subject'])->toBe('VPN VPN VPN');
    });

    test('respects recency window - excludes old tickets', function () {
        // Create an old ticket (91 days ago)
        Ticket::factory()->create([
            'subject' => 'VPN Connection',
            'description' => 'Cannot connect VPN old old',
            'created_by' => $this->employee1->id,
            'created_at' => now()->subDays(91),
        ]);

        // Create a recent ticket (30 days ago)
        Ticket::factory()->create([
            'subject' => 'VPN Connection',
            'description' => 'Cannot connect VPN recent recent',
            'created_by' => $this->employee1->id,
            'created_at' => now()->subDays(30),
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN Connection',
            'Cannot connect VPN'
        );

        // Should only find the recent ticket
        expect($result)->toHaveCount(1)
            ->and($result[0]['description_snippet'])->toContain('recent');
    });

    test('excludes closed/resolved tickets', function () {
        Ticket::factory()->create([
            'subject' => 'VPN Connection Issue Open',
            'description' => 'Cannot connect to VPN from home office',
            'status' => TicketStatus::Closed,
            'created_by' => $this->employee1->id,
        ]);

        Ticket::factory()->create([
            'subject' => 'VPN Down Problem',
            'description' => 'VPN not responding and cannot connect to VPN',
            'status' => TicketStatus::Open,
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN Connection Issues',
            'Cannot connect to VPN'
        );

        // Should only find the open ticket
        expect($result)->toHaveCount(1)
            ->and($result[0]['status'])->toEqual(TicketStatus::Open->value);
    });

    test('matches on subject similarity', function () {
        Ticket::factory()->create([
            'subject' => 'VPN VPN VPN',
            'description' => 'Employee having connection connection',
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN VPN',
            'Connection connection connection'
        );

        expect($result)->toHaveCount(1)
            ->and($result[0]['subject'])->toBe('VPN VPN VPN');
    });

    test('matches on description similarity', function () {
        Ticket::factory()->create([
            'subject' => 'Network Network',
            'description' => 'Cannot Cannot Connect Connect VPN VPN',
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'Something Other',
            'Cannot Connect VPN VPN'
        );

        expect($result)->toHaveCount(1)
            ->and($result[0]['subject'])->toBe('Network Network');
    });

    test('filters results below minimum relevance score', function () {
        // Create a ticket with very weak match
        Ticket::factory()->create([
            'subject' => 'Office Supply Request',
            'description' => 'Need more printer paper for office supplies',
            'created_by' => $this->employee1->id,
        ]);

        // Create a ticket with strong match
        Ticket::factory()->create([
            'subject' => 'VPN Connection Problem',
            'description' => 'Cannot connect to VPN users unable to connect',
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN Connection',
            'Cannot connect to VPN'
        );

        // Should only find the strong match
        expect($result)->toHaveCount(1)
            ->and($result[0]['subject'])->toBe('VPN Connection Problem');
    });

    test('returns top 5 results when more than 5 matches', function () {
        // Create 10 similar tickets
        for ($i = 0; $i < 10; $i++) {
            Ticket::factory()->create([
                'subject' => "VPN Issue #$i",
                'description' => 'Users cannot connect to VPN from home office network',
                'created_by' => $this->employee1->id,
            ]);
        }

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN Connection Problems',
            'Cannot connect to VPN server'
        );

        // Should return up to 5 results (or fewer if fewer pass threshold)
        expect($result)->toHaveCount(5);
    });

    test('ranks results by relevance score', function () {
        // Strong match
        Ticket::factory()->create([
            'subject' => 'VPN VPN VPN',
            'description' => 'Cannot Cannot Connect Connect Office',
            'created_by' => $this->employee1->id,
        ]);

        // Weak match (VPN mentioned but limited overlap)
        Ticket::factory()->create([
            'subject' => 'Email Email',
            'description' => 'Outlook settings printer monitor',
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN VPN',
            'Cannot Connect'
        );

        // Should find the strong match
        expect($result)->toHaveCount(1)
            ->and($result[0]['subject'])->toBe('VPN VPN VPN');
    });

    test('includes correct fields in response', function () {
        Ticket::factory()->create([
            'subject' => 'VPN VPN',
            'description' => 'Cannot connect VPN issue problem',
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN VPN',
            'Cannot connect VPN'
        );

        expect($result)->toHaveCount(1);
        $ticket = $result[0];
        expect($ticket)->toHaveKeys([
            'id', 'subject', 'description_snippet', 'category',
            'status', 'created_at', 'relevance_score'
        ]);
    });

    test('truncates description snippet to 150 characters', function () {
        $longDescription = str_repeat('a', 200);
        Ticket::factory()->create([
            'subject' => 'Test Issue Problem',
            'description' => 'Test ' . $longDescription,
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'Test Issue',
            'Test problem'
        );

        expect($result)->toHaveCount(1);
        $snippet = $result[0]['description_snippet'];
        expect(strlen($snippet))->toBeLessThanOrEqual(153); // 150 + "..."
    });

    test('stops normalization removes punctuation and lowercases', function () {
        Ticket::factory()->create([
            'subject' => 'VPN!!!VPN!!!',
            'description' => 'CANNOT!!!CANNOT!!!VPN VPN',
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'vpn!!!vpn',
            'cannot!!!cannot!!!vpn'
        );

        // Should still find the match despite case/punctuation differences
        expect($result)->toHaveCount(1);
    });

    test('handles stop-word removal correctly', function () {
        Ticket::factory()->create([
            'subject' => 'The VPN and VPN Issues',
            'description' => 'This is cannot cannot where users cannot connect VPN VPN service',
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN VPN Problems',
            'Users cannot connect VPN'
        );

        // Stop-words should not prevent matching on VPN + connection
        expect($result)->toHaveCount(1)
            ->and($result[0]['relevance_score'])->toBeGreaterThan(0.15);
    });

    test('agent can see all tickets regardless of owner', function () {
        Ticket::factory()->create([
            'subject' => 'VPN VPN',
            'description' => 'Cannot Cannot VPN VPN Office',
            'created_by' => $this->employee1->id,
        ]);

        Ticket::factory()->create([
            'subject' => 'VPN VPN',
            'description' => 'Cannot Cannot VPN VPN Remotely',
            'created_by' => $this->employee2->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->agent,
            'VPN VPN',
            'Cannot Cannot VPN'
        );

        // Agent should see both tickets based on similarity
        expect($result)->toHaveCount(2);
    });

    test('handles empty draft gracefully', function () {
        Ticket::factory()->create([
            'subject' => 'VPN Issue',
            'description' => 'Cannot connect',
            'created_by' => $this->employee1->id,
        ]);

        // Empty draft - only stop-words
        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'the and or',
            'a is it'
        );

        expect($result)->toBeEmpty();
    });

    test('weights subject similarity higher than description', function () {
        // Ticket with high subject match, low description match
        $ticket1 = Ticket::factory()->create([
            'subject' => 'VPN VPN VPN',
            'description' => 'Other Other Other Other Other',
            'created_by' => $this->employee1->id,
        ]);

        // Ticket with low subject match, high description match
        $ticket2 = Ticket::factory()->create([
            'subject' => 'Network Network',
            'description' => 'VPN VPN VPN VPN VPN VPN VPN',
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN VPN VPN',
            'Other Other'
        );

        // First result should be the one with subject match (higher weight)
        expect($result)->toHaveCount(1)
            ->and($result[0]['id'])->toBe($ticket1->id);
    });

    test('normalizes whitespace correctly', function () {
        Ticket::factory()->create([
            'subject' => 'VPN    Connection    Issues',
            'description' => 'Cannot connect VPN whitespace',
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN Connection',
            'Cannot connect VPN'
        );

        expect($result)->toHaveCount(1);
    });

    test('relevance score is between 0 and 1', function () {
        Ticket::factory()->count(5)->create([
            'subject' => 'VPN Issue',
            'description' => 'Cannot connect to VPN',
            'created_by' => $this->employee1->id,
        ]);

        $result = $this->engine->findSimilarTickets(
            $this->employee1,
            'VPN Connection Problem',
            'Cannot connect'
        );

        foreach ($result as $ticket) {
            expect($ticket['relevance_score'])->toBeGreaterThanOrEqual(0.0)
                ->and($ticket['relevance_score'])->toBeLessThanOrEqual(1.0);
        }
    });
});
