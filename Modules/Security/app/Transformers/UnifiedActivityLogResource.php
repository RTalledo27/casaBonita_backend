<?php

namespace Modules\Security\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class UnifiedActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null;

        $user = null;
        if (isset($this->user_user_id) && $this->user_user_id) {
            $user = [
                'user_id' => (int) $this->user_user_id,
                'username' => $this->user_username,
                'email' => $this->user_email,
                'name' => trim(($this->user_first_name ?? '') . ' ' . ($this->user_last_name ?? '')),
            ];
        }

        $action = (string) ($this->action ?? '');

        return [
            'id' => (int) $this->id,
            'action' => $action,
            'action_label' => $this->actionLabel($action),
            'details' => $this->details,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->metadata,
            'created_at' => $createdAt,
            'user' => $user,
            'actor_identifier' => $this->actor_identifier ?? null,
            'source' => $this->source ?? null,
        ];
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'login' => 'Inicio de sesión',
            'logout' => 'Cierre de sesión',
            'profile_updated' => 'Perfil actualizado',
            'password_changed' => 'Contraseña cambiada',
            'preferences_updated' => 'Preferencias actualizadas',
            'contract_created' => 'Contrato creado',
            'contract_updated' => 'Contrato actualizado',
            'payment_registered' => 'Pago registrado',
            'lot_assigned' => 'Lote asignado',
            'commission_calculated' => 'Comisión calculada',
            'report_viewed' => 'Reporte visualizado',
            'report_exported' => 'Reporte exportado',
            'data_imported' => 'Datos importados',
            'data_exported' => 'Datos exportados',
            'http_request' => 'Request API',
            'login_failed' => 'Intento de login fallido',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }
}

