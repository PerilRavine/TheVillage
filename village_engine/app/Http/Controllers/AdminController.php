<?php

namespace App\Http\Controllers;

use App\Models\Village;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * AdminController - Village Server Administration
 * 
 * Handles server setup, village creation, and management tools
 */
class AdminController extends Controller
{
    /**
     * Display admin dashboard
     */
    public function dashboard()
    {
        // Get server statistics
        $stats = [
            'totalUsers' => 0, // Will be calculated from users table
            'totalVillages' => Village::count(),
            'activeUsers' => 0, // Will be calculated from active sessions
            'serverUptime' => $this->getServerUptime(),
        ];
        
        return view('admin', compact('stats'));
    }
    
    /**
     * Store new server configuration
     */
    public function storeServer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'server_domain' => 'required|string|max:255|unique:servers',
            'max_population' => 'required|integer|min:10|max:10000',
            'server_description' => 'nullable|string|max:1000',
        ]);
        
        try {
            $server = Server::create([
                'name' => $validated['server_name'],
                'domain' => $validated['server_domain'],
                'max_population' => $validated['max_population'],
                'description' => $validated['server_description'],
                'admin_id' => auth()->id(),
                'status' => 'active',
                'created_at' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Server created successfully',
                'server' => $server,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create server: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Create new village
     */
    public function createVillage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'village_name' => 'required|string|max:255',
            'village_type' => 'required|in:tech,art,science,residential,commercial',
            'village_size' => 'required|in:small,medium,large',
            'reputation_required' => 'required|integer|min:0|max:100',
        ]);
        
        try {
            $village = Village::create([
                'name' => $validated['village_name'],
                'type' => $validated['village_type'],
                'size' => $validated['village_size'],
                'reputation_required' => $validated['reputation_required'],
                'server_id' => 1, // Default to first server
                'admin_id' => auth()->id(),
                'status' => 'active',
                'created_at' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Village created successfully',
                'village' => $village,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create village: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get village statistics
     */
    public function getVillageStats(): JsonResponse
    {
        try {
            $stats = [
                'totalVillages' => Village::count(),
                'villagesByType' => Village::selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'averageReputation' => Village::avg('reputation_required'),
                'totalPopulation' => Village::sum('current_population'),
                'activeVillages' => Village::where('status', 'active')->count(),
            ];
            
            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get network overview
     */
    public function getNetworkOverview(): JsonResponse
    {
        try {
            $servers = Server::withCount('villages')
                ->with(['villages' => function($query) {
                    $query->select('id', 'name', 'type', 'current_population', 'reputation_required');
                }])
                ->get();
            
            return response()->json([
                'servers' => $servers,
                'totalServers' => $servers->count(),
                'totalVillages' => $servers->sum->villages_count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get network overview: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Calculate server uptime
     */
    private function getServerUptime(): string
    {
        // Simple uptime calculation - in real implementation, 
        // this would track actual server start time
        $startTime = strtotime('2024-01-01'); // Placeholder
        $uptime = time() - $startTime;
        
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        
        return "{$days}d {$hours}h";
    }
}
