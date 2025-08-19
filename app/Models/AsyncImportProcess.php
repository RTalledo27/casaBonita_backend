<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Security\Models\User;

class AsyncImportProcess extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'status',
        'file_name',
        'file_path',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'progress_percentage',
        'errors',
        'warnings',
        'summary',
        'started_at',
        'completed_at',
        'user_id',
    ];

    protected $casts = [
        'errors' => 'array',
        'warnings' => 'array',
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress_percentage' => 'decimal:2',
    ];

    /**
     * Get the user that owns the import process.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Check if the import process is completed.
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'failed']);
    }

    /**
     * Check if the import process is in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Update the progress of the import process.
     */
    public function updateProgress(int $processedRows, int $successfulRows = null, int $failedRows = null): void
    {
        $this->processed_rows = $processedRows;
        
        if ($successfulRows !== null) {
            $this->successful_rows = $successfulRows;
        }
        
        if ($failedRows !== null) {
            $this->failed_rows = $failedRows;
        }
        
        if ($this->total_rows > 0) {
            $this->progress_percentage = ($processedRows / $this->total_rows) * 100;
        }
        
        $this->save();
    }

    /**
     * Mark the import process as completed.
     */
    public function markAsCompleted(array $summary = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress_percentage' => 100,
            'summary' => $summary,
        ]);
    }

    /**
     * Mark the import process as failed.
     */
    public function markAsFailed(array $errors = []): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'errors' => $errors,
        ]);
    }

    /**
     * Add an error to the import process.
     */
    public function addError(string $error): void
    {
        $errors = $this->errors ?? [];
        $errors[] = $error;
        $this->errors = $errors;
        $this->save();
    }

    /**
     * Add a warning to the import process.
     */
    public function addWarning(string $warning): void
    {
        $warnings = $this->warnings ?? [];
        $warnings[] = $warning;
        $this->warnings = $warnings;
        $this->save();
    }
}