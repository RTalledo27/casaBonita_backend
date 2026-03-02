<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Security\Models\User;

class Lot extends Model
{
    use HasFactory;

    protected $primaryKey = 'lot_id';

    protected $fillable = [
        'manzana_id',
        'street_type_id',
        'num_lot',
        'area_m2',
        'area_construction_m2',
        'total_price',
        'currency',
        'status',
        'locked_by',
        'lock_reason',
        'locked_at',
        // Campos de sincronización con API externa (LOGICWARE)
        'external_id',
        'external_code',
        'external_sync_at',
        'external_data'
    ];

    protected $casts = [
        'external_sync_at' => 'datetime',
        'external_data' => 'array',
        'locked_at' => 'datetime',
    ];

    // ──── STATUS HELPERS ────

    public function isAvailable(): bool
    {
        return $this->status === 'disponible';
    }

    public function isLocked(): bool
    {
        return !is_null($this->locked_by);
    }

    public function isLockedBy(int $userId): bool
    {
        return $this->locked_by === $userId;
    }

    /**
     * Bloquear lote para un usuario (proceso de venta)
     */
    public function lockFor(int $userId, string $reason = 'Proceso de venta'): bool
    {
        if ($this->isLocked() && !$this->isLockedBy($userId)) {
            return false; // Ya bloqueado por otro usuario
        }

        $this->update([
            'status' => 'en_proceso',
            'locked_by' => $userId,
            'lock_reason' => $reason,
            'locked_at' => now(),
        ]);

        return true;
    }

    /**
     * Desbloquear lote
     */
    public function unlock(string $newStatus = 'disponible'): void
    {
        $this->update([
            'status' => $newStatus,
            'locked_by' => null,
            'lock_reason' => null,
            'locked_at' => null,
        ]);
    }

    // ──── RELACIONES ────

    public function manzana()
    {
        return $this->belongsTo(Manzana::class, 'manzana_id');
    }

    public function streetType()
    {
        return $this->belongsTo(StreetType::class, 'street_type_id', 'street_type_id');
    }

    public function media()
    {
        return $this->hasMany(LotMedia::class, 'lot_id', 'lot_id');
    }

    public function financialTemplate()
    {
        return $this->hasOne(LotFinancialTemplate::class, 'lot_id', 'lot_id');
    }

    public function reservations()
    {
        return $this->hasMany(\Modules\Sales\Models\Reservation::class, 'lot_id', 'lot_id');
    }

    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by', 'user_id');
    }
}
