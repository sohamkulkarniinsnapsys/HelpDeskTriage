<?php

use App\Enums\Role;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    
    $this->agent = User::factory()->create(['role' => Role::Agent]);
    $this->employee = User::factory()->create(['role' => Role::Employee]);
    $this->ticket = Ticket::factory()->create(['created_by' => $this->employee->id]);
});

test('employee can upload attachments to their ticket', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100);

    $response = $this->actingAs($this->employee)
        ->postJson(route('tickets.attachments.store', $this->ticket), [
            'attachments' => [$file],
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('attachments', [
        'ticket_id' => $this->ticket->id,
        'original_filename' => 'document.pdf',
    ]);

    Storage::disk('local')->assertExists(
        Attachment::where('ticket_id', $this->ticket->id)->first()->stored_path
    );
});

test('employee can upload multiple attachments', function () {
    $files = [
        UploadedFile::fake()->create('doc1.pdf', 100),
        UploadedFile::fake()->create('doc2.pdf', 100),
        UploadedFile::fake()->create('image.jpg', 50),
    ];

    $response = $this->actingAs($this->employee)
        ->postJson(route('tickets.attachments.store', $this->ticket), [
            'attachments' => $files,
        ]);

    $response->assertCreated();

    expect(Attachment::where('ticket_id', $this->ticket->id)->count())->toBe(3);
});

test('attachment upload validates file size limit', function () {
    $largeFile = UploadedFile::fake()->create('large.pdf', 15000); // 15MB

    $response = $this->actingAs($this->employee)
        ->postJson(route('tickets.attachments.store', $this->ticket), [
            'attachments' => [$largeFile],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('attachments.0');
});

test('attachment upload validates file type', function () {
    $invalidFile = UploadedFile::fake()->create('script.exe', 100);

    $response = $this->actingAs($this->employee)
        ->postJson(route('tickets.attachments.store', $this->ticket), [
            'attachments' => [$invalidFile],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('attachments.0');
});

test('attachment upload validates maximum number of files', function () {
    $files = [];
    for ($i = 0; $i < 6; $i++) {
        $files[] = UploadedFile::fake()->create("doc{$i}.pdf", 100);
    }

    $response = $this->actingAs($this->employee)
        ->postJson(route('tickets.attachments.store', $this->ticket), [
            'attachments' => $files,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('attachments');
});

test('employee cannot upload attachments to other tickets', function () {
    $otherTicket = Ticket::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 100);

    $response = $this->actingAs($this->employee)
        ->postJson(route('tickets.attachments.store', $otherTicket), [
            'attachments' => [$file],
        ]);

    $response->assertForbidden();
});

test('agent can upload attachments to any ticket', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100);

    $response = $this->actingAs($this->agent)
        ->postJson(route('tickets.attachments.store', $this->ticket), [
            'attachments' => [$file],
        ]);

    $response->assertCreated();
});

test('employee can download their ticket attachments', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100);
    
    Storage::disk('local')->putFileAs(
        "attachments/tickets/{$this->ticket->id}",
        $file,
        'test.pdf'
    );

    $attachment = Attachment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'original_filename' => 'document.pdf',
        'stored_path' => "attachments/tickets/{$this->ticket->id}/test.pdf",
    ]);

    $response = $this->actingAs($this->employee)
        ->get(route('attachments.download', $attachment));

    $response->assertOk()
        ->assertDownload('document.pdf');
});

test('employee cannot download attachments from other tickets', function () {
    $otherTicket = Ticket::factory()->create();
    $attachment = Attachment::factory()->create([
        'ticket_id' => $otherTicket->id,
    ]);

    $response = $this->actingAs($this->employee)
        ->get(route('attachments.download', $attachment));

    $response->assertForbidden();
});

test('agent can download all attachments', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100);
    
    Storage::disk('local')->putFileAs(
        "attachments/tickets/{$this->ticket->id}",
        $file,
        'test.pdf'
    );

    $attachment = Attachment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'original_filename' => 'document.pdf',
        'stored_path' => "attachments/tickets/{$this->ticket->id}/test.pdf",
    ]);

    $response = $this->actingAs($this->agent)
        ->get(route('attachments.download', $attachment));

    $response->assertOk();
});

test('employee can delete their ticket attachments', function () {
    $attachment = Attachment::factory()->create([
        'ticket_id' => $this->ticket->id,
    ]);

    Storage::disk('local')->put($attachment->stored_path, 'content');

    $response = $this->actingAs($this->employee)
        ->deleteJson(route('attachments.destroy', $attachment));

    $response->assertNoContent();

    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    Storage::disk('local')->assertMissing($attachment->stored_path);
});

test('employee cannot delete attachments from other tickets', function () {
    $otherTicket = Ticket::factory()->create();
    $attachment = Attachment::factory()->create([
        'ticket_id' => $otherTicket->id,
    ]);

    $response = $this->actingAs($this->employee)
        ->deleteJson(route('attachments.destroy', $attachment));

    $response->assertForbidden();
});

test('agent can delete any attachment', function () {
    $attachment = Attachment::factory()->create([
        'ticket_id' => $this->ticket->id,
    ]);

    Storage::disk('local')->put($attachment->stored_path, 'content');

    $response = $this->actingAs($this->agent)
        ->deleteJson(route('attachments.destroy', $attachment));

    $response->assertNoContent();
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
});

test('attachment list can be retrieved for a ticket', function () {
    Attachment::factory()->count(3)->create([
        'ticket_id' => $this->ticket->id,
    ]);

    $response = $this->actingAs($this->employee)
        ->getJson(route('tickets.attachments.index', $this->ticket));

    $response->assertOk()
        ->assertJsonCount(3);
});

test('ticket can be created with attachments', function () {
    $files = [
        UploadedFile::fake()->create('doc1.pdf', 100),
        UploadedFile::fake()->create('doc2.pdf', 100),
    ];

    $response = $this->actingAs($this->employee)
        ->postJson(route('tickets.store'), [
            'subject' => 'Test ticket',
            'description' => 'Description',
            'category' => 'hardware',
            'severity' => 3,
            'attachments' => $files,
        ]);

    $response->assertCreated();

    // Get the created ticket from the response
    $ticketId = $response->json('id');
    $ticket = Ticket::find($ticketId);
    
    expect($ticket->attachments)->toHaveCount(2);
});
