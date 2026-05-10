<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PeerDiscoveryRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $userId,
        public string $userLocation,
        public array $userPreferences,
        public string $sessionId
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('peer-discovery'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'peer.discovery.requested';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'location' => $this->userLocation,
            'preferences' => $this->userPreferences,
            'session_id' => $this->sessionId,
            'timestamp' => now()->toISOString(),
        ];
    }
}
