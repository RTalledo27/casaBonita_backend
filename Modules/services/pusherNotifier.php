<?php

namespace Modules\services;

use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

class PusherNotifier
{
    protected Pusher $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options')
        );
    }

    public function notify(string $channel, string $event, array $data): void
    {
        try {
            $this->pusher->trigger($channel, $event, $data);
        } catch (\Throwable $e) {
            Log::error('Pusher notification failed', ['error' => $e->getMessage()]);
        }
    }
}
