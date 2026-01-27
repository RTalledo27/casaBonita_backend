<?php

namespace Modules\Security\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->whenLoaded('user');

        return [
            'id' => $this->id,
            'action' => $this->action,
            'action_label' => method_exists($this->resource, 'getActionLabel') ? $this->getActionLabel() : null,
            'details' => $this->details,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $user ? [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            ] : null,
        ];
    }
}

