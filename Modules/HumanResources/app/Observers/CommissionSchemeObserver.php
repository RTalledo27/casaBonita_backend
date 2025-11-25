<?php

namespace Modules\HumanResources\Observers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\HumanResources\Models\CommissionScheme;

/**
 * Observer para actualizar automáticamente las fechas efectivas de esquemas de comisión
 * 
 * Regla de negocio:
 * - Cuando se crea un nuevo esquema con effective_from en un mes futuro,
 *   los esquemas anteriores se cierran automáticamente estableciendo
 *   sus effective_to al día anterior del nuevo effective_from
 */
class CommissionSchemeObserver
{
    /**
     * Handle the CommissionScheme "creating" event.
     * 
     * Se ejecuta ANTES de guardar el nuevo esquema.
     * Actualiza los esquemas previos para cerrarlos automáticamente.
     *
     * @param  \Modules\HumanResources\Models\CommissionScheme  $scheme
     * @return void
     */
    public function creating(CommissionScheme $scheme)
    {
        // Solo procesar si tiene effective_from definido
        if (!$scheme->effective_from) {
            Log::info('[CommissionSchemeObserver] Esquema sin effective_from, no se actualizan esquemas previos');
            return;
        }

        $newEffectiveFrom = Carbon::parse($scheme->effective_from);
        
        Log::info('[CommissionSchemeObserver] Creando nuevo esquema', [
            'name' => $scheme->name,
            'effective_from' => $newEffectiveFrom->toDateString()
        ]);

        // Buscar esquemas que:
        // 1. Empezaron ANTES de este nuevo esquema
        // 2. No tienen effective_to O su effective_to es posterior al nuevo effective_from
        $previousSchemes = CommissionScheme::where('effective_from', '<', $newEffectiveFrom)
            ->where(function ($q) use ($newEffectiveFrom) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $newEffectiveFrom);
            })
            ->get();

        if ($previousSchemes->isEmpty()) {
            Log::info('[CommissionSchemeObserver] No hay esquemas previos que cerrar');
            return;
        }

        // Calcular la fecha de cierre: un día antes del nuevo effective_from
        $closingDate = $newEffectiveFrom->copy()->subDay();

        foreach ($previousSchemes as $prevScheme) {
            $oldTo = $prevScheme->effective_to;
            $prevScheme->effective_to = $closingDate;
            $prevScheme->save();

            Log::info('[CommissionSchemeObserver] ✅ Esquema anterior cerrado', [
                'prev_scheme_id' => $prevScheme->id,
                'prev_scheme_name' => $prevScheme->name,
                'old_effective_to' => $oldTo ? $oldTo->toDateString() : 'NULL',
                'new_effective_to' => $closingDate->toDateString(),
                'reason' => "Nuevo esquema '{$scheme->name}' empieza el {$newEffectiveFrom->toDateString()}"
            ]);
        }

        Log::info('[CommissionSchemeObserver] Se cerraron ' . $previousSchemes->count() . ' esquemas previos');
    }

    /**
     * Handle the CommissionScheme "updating" event.
     * 
     * Si se actualiza el effective_from, recalcular los cierres de esquemas previos.
     *
     * @param  \Modules\HumanResources\Models\CommissionScheme  $scheme
     * @return void
     */
    public function updating(CommissionScheme $scheme)
    {
        // Si cambió el effective_from, necesitamos recalcular
        if ($scheme->isDirty('effective_from') && $scheme->effective_from) {
            $newEffectiveFrom = Carbon::parse($scheme->effective_from);
            
            Log::info('[CommissionSchemeObserver] Actualizando effective_from de esquema existente', [
                'scheme_id' => $scheme->id,
                'old_from' => $scheme->getOriginal('effective_from'),
                'new_from' => $newEffectiveFrom->toDateString()
            ]);

            // Buscar esquemas que ahora necesitan cerrarse
            $previousSchemes = CommissionScheme::where('id', '!=', $scheme->id)
                ->where('effective_from', '<', $newEffectiveFrom)
                ->where(function ($q) use ($newEffectiveFrom) {
                    $q->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $newEffectiveFrom);
                })
                ->get();

            if ($previousSchemes->isNotEmpty()) {
                $closingDate = $newEffectiveFrom->copy()->subDay();

                foreach ($previousSchemes as $prevScheme) {
                    $prevScheme->effective_to = $closingDate;
                    $prevScheme->save();

                    Log::info('[CommissionSchemeObserver] ✅ Esquema anterior cerrado por actualización', [
                        'prev_scheme_id' => $prevScheme->id,
                        'new_effective_to' => $closingDate->toDateString()
                    ]);
                }
            }
        }
    }
}
