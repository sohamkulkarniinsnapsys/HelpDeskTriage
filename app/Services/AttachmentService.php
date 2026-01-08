<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    /**
     * Upload and attach files to a ticket.
     *
     * @param Ticket $ticket
     * @param array<UploadedFile> $files
     * @return array<Attachment>
     */
    public function uploadAttachments(Ticket $ticket, array $files): array
    {
        $attachments = [];

        foreach ($files as $file) {
            $attachments[] = $this->uploadSingleAttachment($ticket, $file);
        }

        return $attachments;
    }

    /**
     * Upload a single file and create an attachment record.
     */
    public function uploadSingleAttachment(Ticket $ticket, UploadedFile $file): Attachment
    {
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Generate a unique filename to prevent collisions
        $storedFilename = Str::uuid() . '.' . $extension;
        
        // Store in a ticket-specific directory for organization
        $path = "attachments/tickets/{$ticket->id}/{$storedFilename}";
        
        // Store the file privately (not publicly accessible)
        Storage::disk('local')->putFileAs(
            "attachments/tickets/{$ticket->id}",
            $file,
            $storedFilename
        );

        return Attachment::create([
            'ticket_id' => $ticket->id,
            'original_filename' => $originalFilename,
            'stored_path' => $path,
            'mime_type' => $mimeType,
            'size' => $size,
        ]);
    }

    /**
     * Delete an attachment and its file.
     */
    public function deleteAttachment(Attachment $attachment): bool
    {
        // Delete the physical file
        if (Storage::disk('local')->exists($attachment->stored_path)) {
            Storage::disk('local')->delete($attachment->stored_path);
        }

        // Delete the database record
        return $attachment->delete();
    }

    /**
     * Get the file path for downloading an attachment.
     */
    public function getAttachmentPath(Attachment $attachment): string
    {
        return Storage::disk('local')->path($attachment->stored_path);
    }

    /**
     * Check if an attachment file exists.
     */
    public function attachmentExists(Attachment $attachment): bool
    {
        return Storage::disk('local')->exists($attachment->stored_path);
    }

    /**
     * Get the size of all attachments for a ticket in bytes.
     */
    public function getTotalAttachmentSize(Ticket $ticket): int
    {
        return $ticket->attachments()->sum('size');
    }

    /**
     * Format file size in human-readable format.
     */
    public function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
