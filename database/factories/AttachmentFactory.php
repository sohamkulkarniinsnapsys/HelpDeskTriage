<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->word() . '.' . fake()->fileExtension();

        return [
            'ticket_id' => Ticket::factory(),
            'original_filename' => $filename,
            'stored_path' => 'attachments/' . fake()->uuid() . '/' . $filename,
            'mime_type' => fake()->mimeType(),
            'size' => fake()->numberBetween(1024, 10485760), // 1KB to 10MB
        ];
    }
}
