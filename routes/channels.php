<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->user_id === (int) $id;
});

// Canal de notificaciones para cada usuario
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->user_id === (int) $userId;
});

// Canal de Service Desk para actualizaciones de tickets en tiempo real
Broadcast::channel('servicedesk.{userId}', function ($user, $userId) {
    return (int) $user->user_id === (int) $userId;
});

// Canal global de Service Desk para administradores (público)
Broadcast::channel('servicedesk.updates', function () {
    return true; // Público para todos los autenticados
});

// Canal público de webhooks (no requiere autenticación)
Broadcast::channel('webhooks', function () {
    return true; // Público para todos
});
