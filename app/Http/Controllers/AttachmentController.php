<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadAttachmentRequest;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    public function __construct(
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Display a listing of attachments for a ticket.
     */
    public function index(Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $attachments = $ticket->attachments()->get();

        return response()->json($attachments);
    }

    /**
     * Upload attachments to a ticket.
     */
    public function store(UploadAttachmentRequest $request, Ticket $ticket): JsonResponse
    {
        $attachments = $this->attachmentService->uploadAttachments(
            $ticket,
            $request->file('attachments')
        );

        return response()->json($attachments, 201);
    }

    /**
     * Download an attachment file.
     * Authorization enforced - users can only download attachments
     * from tickets they have access to.
     */
    public function download(Attachment $attachment): BinaryFileResponse
    {
        Gate::authorize('view', $attachment);

        if (!$this->attachmentService->attachmentExists($attachment)) {
            abort(404, 'File not found.');
        }

        $path = $this->attachmentService->getAttachmentPath($attachment);

        return response()->download(
            $path,
            $attachment->original_filename,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'attachment; filename="' . $attachment->original_filename . '"',
            ]
        );
    }

    /**
     * Remove the specified attachment.
     */
    public function destroy(Attachment $attachment): JsonResponse
    {
        Gate::authorize('delete', $attachment);

        $this->attachmentService->deleteAttachment($attachment);

        return response()->json(null, 204);
    }
}
