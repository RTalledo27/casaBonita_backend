<?php

namespace Modules\ServiceDesk\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\ServiceDesk\Models\ServiceRequest;
use Modules\ServiceDesk\Models\TicketAttachment;
use Modules\ServiceDesk\Services\ServiceDeskNotificationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    protected ServiceDeskNotificationService $notificationService;

    // Allowed file types
    private const ALLOWED_MIMES = [
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // Text
        'text/plain',
        'text/csv',
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct(ServiceDeskNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
    }

    /**
     * List attachments for a ticket
     */
    public function index(int $ticketId): JsonResponse
    {
        $ticket = ServiceRequest::findOrFail($ticketId);
        
        // Authorization check
        $this->authorize('view', $ticket);

        $attachments = $ticket->attachments()
            ->with('uploader:user_id,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $attachments,
            'count' => $attachments->count()
        ]);
    }

    /**
     * Upload attachment to a ticket
     */
    public function store(Request $request, int $ticketId): JsonResponse
    {
        $ticket = ServiceRequest::findOrFail($ticketId);
        
        // Authorization check - user can add comment if they can update
        $this->authorize('update', $ticket);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB in KB
            ],
            'description' => 'nullable|string|max:255'
        ]);

        $file = $request->file('file');
        
        // Validate mime type
        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
            return response()->json([
                'message' => 'Tipo de archivo no permitido. Solo se permiten: imágenes (JPG, PNG, GIF, WebP), documentos (PDF, Word, Excel) y texto.',
                'allowed_types' => self::ALLOWED_MIMES
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validate file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return response()->json([
                'message' => 'El archivo supera el tamaño máximo permitido de 10MB.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $storedName = Str::uuid() . '.' . $extension;
        $directory = "service-desk/attachments/ticket-{$ticketId}";
        
        // Store file
        $path = $file->storeAs($directory, $storedName, 'local');

        // Create attachment record
        $attachment = TicketAttachment::create([
            'ticket_id' => $ticketId,
            'uploaded_by' => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        // Load uploader relation
        $attachment->load('uploader:user_id,first_name,last_name');

        // Notify about the new attachment
        $this->notificationService->notifyNewComment(
            $ticket,
            "Archivo adjuntado: {$file->getClientOriginalName()}",
            'archivo_adjunto'
        );

        return response()->json([
            'message' => 'Archivo adjuntado exitosamente',
            'data' => $attachment
        ], Response::HTTP_CREATED);
    }

    /**
     * Download an attachment
     */
    public function download(int $attachmentId): StreamedResponse|JsonResponse
    {
        $attachment = TicketAttachment::with('ticket')->findOrFail($attachmentId);
        
        // Authorization - user must be able to view the ticket
        $this->authorize('view', $attachment->ticket);

        if (!Storage::disk('local')->exists($attachment->file_path)) {
            return response()->json([
                'message' => 'El archivo no existe en el servidor.'
            ], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
            ]
        );
    }

    /**
     * Delete an attachment
     */
    public function destroy(int $attachmentId): JsonResponse
    {
        $attachment = TicketAttachment::with('ticket')->findOrFail($attachmentId);
        
        // Authorization - only uploader or admin can delete
        $user = auth()->user();
        $canDelete = $attachment->uploaded_by === $user->user_id 
            || $user->hasRole('Administrador')
            || $user->can('service-desk.attachments.delete');

        if (!$canDelete) {
            return response()->json([
                'message' => 'No tienes permiso para eliminar este archivo.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Store info for notification
        $fileName = $attachment->original_name;
        $ticket = $attachment->ticket;

        // Delete (model event will delete file from storage)
        $attachment->delete();

        return response()->json([
            'message' => "Archivo '{$fileName}' eliminado exitosamente"
        ]);
    }

    /**
     * Get preview URL for images
     */
    public function preview(int $attachmentId): StreamedResponse|JsonResponse
    {
        $attachment = TicketAttachment::with('ticket')->findOrFail($attachmentId);
        
        // Authorization
        $this->authorize('view', $attachment->ticket);

        if (!$attachment->is_image) {
            return response()->json([
                'message' => 'Solo las imágenes pueden ser previsualizadas.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!Storage::disk('local')->exists($attachment->file_path)) {
            return response()->json([
                'message' => 'El archivo no existe en el servidor.'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->stream(function () use ($attachment) {
            echo Storage::disk('local')->get($attachment->file_path);
        }, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="' . $attachment->original_name . '"',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
