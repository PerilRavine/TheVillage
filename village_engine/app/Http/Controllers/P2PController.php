<?php

namespace App\Http\Controllers;

use App\Events\PeerDiscoveryRequested;
use App\Events\PeerDiscoveryResponse;
use App\Models\User;
use App\Services\ReputationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class P2PController extends Controller
{
    public function __construct(
        private ReputationService $reputationService
    ) {}

    /**
     * Request peer discovery for current user
     */
    public function requestPeerDiscovery(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'location' => 'required|string|max:255',
            'preferences' => 'array',
            'preferences.max_distance' => 'numeric|min:0|max:1000',
            'preferences.min_reputation' => 'numeric|min:0|max:100',
        ]);

        // Broadcast peer discovery request
        broadcast(new PeerDiscoveryRequested(
            userId: $user->id,
            userLocation: $validated['location'],
            userPreferences: $validated['preferences'] ?? [],
            sessionId: $request->session()->getId()
        ));

        return response()->json([
            'message' => 'Peer discovery request sent',
            'session_id' => $request->session()->getId(),
            'user_reputation' => $this->reputationService->calculateUserReputation($user),
        ]);
    }

    /**
     * Respond to peer discovery request
     */
    public function respondToPeerDiscovery(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'requester_id' => 'required|string',
            'session_id' => 'required|string',
        ]);

        $requester = User::find($validated['requester_id']);
        
        if (!$requester) {
            return response()->json(['error' => 'Requester not found'], 404);
        }

        // Check if user reputation is sufficient for peer discovery
        $userReputation = $this->reputationService->calculateUserReputation($user);
        if ($userReputation->total < 10.0) {
            return response()->json(['error' => 'Insufficient reputation for peer discovery'], 403);
        }

        // Send peer discovery response to requester
        broadcast(new PeerDiscoveryResponse(
            requesterId: $validated['requester_id'],
            responderId: $user->id,
            responderLocation: $user->profile_data['location'] ?? 'Unknown',
            responderProfile: [
                'username' => $user->username,
                'display_name' => $user->display_name,
                'role' => $user->role,
                'bio' => $user->bio,
                'public_profile' => $user->public_profile,
            ],
            responderReputation: $userReputation->total,
            sessionId: $validated['session_id']
        ));

        return response()->json([
            'message' => 'Peer discovery response sent',
            'requester_id' => $validated['requester_id'],
        ]);
    }

    /**
     * Get available peers for current user
     */
    public function getAvailablePeers(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get users within 3 degrees of separation
        $peers = $this->reputationService->getPeersWithinThreeDegrees($user);
        
        return response()->json([
            'peers' => $peers->map(function ($peer) {
                $reputation = $this->reputationService->calculateUserReputation($peer);
                return [
                    'id' => $peer->id,
                    'username' => $peer->username,
                    'display_name' => $peer->display_name,
                    'role' => $peer->role,
                    'reputation' => $reputation->total,
                    'location' => $peer->profile_data['location'] ?? null,
                    'public_profile' => $peer->public_profile,
                    'last_activity' => $peer->last_activity_at,
                ];
            }),
            'total' => $peers->count(),
        ]);
    }

    /**
     * Get peer connection status
     */
    public function getPeerStatus(string $peerId): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $peer = User::find($peerId);
        
        if (!$peer) {
            return response()->json(['error' => 'Peer not found'], 404);
        }

        // Calculate trust relationship between users
        $trustLevel = $this->reputationService->calculateTrustLevel($user, $peer);
        
        return response()->json([
            'peer' => [
                'id' => $peer->id,
                'username' => $peer->username,
                'display_name' => $peer->display_name,
                'role' => $peer->role,
                'reputation' => $this->reputationService->calculateUserReputation($peer)->total,
                'last_activity' => $peer->last_activity_at,
                'online' => $peer->last_activity_at && $peer->last_activity_at->gt(now()->subMinutes(5)),
            ],
            'trust_level' => $trustLevel,
            'can_connect' => $trustLevel >= 0.5, // Minimum trust level for connection
        ]);
    }
}
