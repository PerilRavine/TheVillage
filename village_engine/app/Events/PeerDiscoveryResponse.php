<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PeerDiscoveryResponse implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $requesterId,
        public string $responderId,
        public string $responderLocation,
        public array $responderProfile,
        public string $responderReputation,
        public string $sessionId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('peer-discovery.' . $this->requesterId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'peer.discovery.response';
    }

    public function broadcastWith(): array
    {
        return [
            'responder_id' => $this->responderId,
            'location' => $this->responderLocation,
            'profile' => $this->responderProfile,
            'reputation' => $this->responderReputation,
            'session_id' => $this->sessionId,
            'timestamp' => now()->toISOString(),
        ];
    }
}
