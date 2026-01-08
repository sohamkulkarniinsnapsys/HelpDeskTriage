<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(),
            'description' => fake()->paragraphs(3, true),
            'category' => fake()->randomElement(TicketCategory::cases()),
            'severity' => fake()->numberBetween(1, 5),
            // Default to open to avoid random closed/resolved tickets breaking similarity tests
            'status' => TicketStatus::Open,
            'created_by' => User::factory(),
            'assigned_to' => fake()->boolean(50) ? User::factory() : null,
        ];
    }

    /**
     * Indicate that the ticket is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Open,
            'assigned_to' => null,
        ]);
    }

    /**
     * Indicate that the ticket is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::InProgress,
        ]);
    }

    /**
     * Indicate that the ticket is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Resolved,
        ]);
    }

    /**
     * Indicate that the ticket is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Closed,
        ]);
    }

    /**
     * Indicate that the ticket is unassigned.
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => null,
        ]);
    }
}
