<?php

namespace Modules\ServiceDesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Modules\Security\Models\User;

class TicketAttachment extends Model
{
    protected $primaryKey = 'attachment_id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'ticket_id',
        'uploaded_by',
        'original_name',
        'stored_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['download_url', 'is_image', 'human_size'];

    // Relationship with ticket
    public function ticket()
    {
        return $this->belongsTo(ServiceRequest::class, 'ticket_id', 'ticket_id');
    }

    // Relationship with uploader
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }

    // Get download URL
    public function getDownloadUrlAttribute(): string
    {
        return url("api/v1/service-desk/attachments/{$this->attachment_id}/download");
    }

    // Check if file is an image
    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    // Human-readable file size
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // Get file extension
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->original_name, PATHINFO_EXTENSION);
    }

    // Delete file from storage when model is deleted
    protected static function booted()
    {
        static::deleting(function (TicketAttachment $attachment) {
            if (Storage::disk('local')->exists($attachment->file_path)) {
                Storage::disk('local')->delete($attachment->file_path);
            }
        });
    }
}
