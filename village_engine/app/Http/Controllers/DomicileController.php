<?php

namespace App\Http\Controllers;

use App\Models\Domicile;
use App\Models\DomicileContent;
use App\Models\BrandTracking;
use App\Models\DomicileAccessLog;
use App\Models\DomicileAnalytics;
use App\Models\DomicileSharedAccess;
use App\Services\DomicileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * DomicileController - Manage personal encrypted spaces
 * 
 * Provides REST API for domicile management, content handling,
 * brand tracking, and analytics
 */
class DomicileController extends Controller
{
    public function __construct(
        private DomicileService $domicileService
    ) {}

    /**
     * Get user's domiciles
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $domiciles = $this->domicileService->getUserDomiciles($user);

        return response()->json([
            'domiciles' => $domiciles,
            'total' => count($domiciles),
        ]);
    }

    /**
     * Create new domicile
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'theme' => 'string|max:50',
            'privacy_settings' => 'array',
            'is_public' => 'boolean',
            'brand_tracking_enabled' => 'boolean',
            'analytics_enabled' => 'boolean',
            'storage_quota' => 'integer|min:1048576', // 1MB minimum
        ]);

        try {
            $domicile = $this->domicileService->createDomicile($user, $validated);
            
            return response()->json([
                'message' => 'Domicile created successfully',
                'domicile' => $domicile,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create domicile',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific domicile
     */
    public function show(Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if (!$domicile->hasAccess($user)) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        return response()->json([
            'domicile' => $domicile,
            'analytics' => $this->domicileService->getDomicileAnalytics($domicile),
        ]);
    }

    /**
     * Update domicile
     */
    public function update(Request $request, Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if ($domicile->user_id !== $user->id) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string|max:1000',
            'theme' => 'string|max:50',
            'privacy_settings' => 'array',
            'is_public' => 'boolean',
            'brand_tracking_enabled' => 'boolean',
            'analytics_enabled' => 'boolean',
            'storage_quota' => 'integer|min:1048576',
        ]);

        $domicile->update($validated);

        return response()->json([
            'message' => 'Domicile updated successfully',
            'domicile' => $domicile,
        ]);
    }

    /**
     * Delete domicile
     */
    public function destroy(Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if ($domicile->user_id !== $user->id) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        $domicile->delete();

        return response()->json([
            'message' => 'Domicile deleted successfully',
        ]);
    }

    /**
     * Upload content to domicile
     */
    public function uploadContent(Request $request, Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if (!$domicile->hasAccess($user)) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        if ($domicile->isStorageQuotaExceeded()) {
            return response()->json([
                'error' => 'Storage quota exceeded',
                'usage' => $domicile->storage_used,
                'quota' => $domicile->storage_quota,
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'content_type' => 'required|string|in:document,image,video,audio,code,tool,other',
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:102400', // 100MB max
            'access_level' => 'string|in:private,shared,public',
            'tags' => 'array',
            'metadata' => 'array',
            'is_public' => 'boolean',
        ]);

        try {
            $content = $this->domicileService->uploadContent($domicile, $user, $validated);
            
            return response()->json([
                'message' => 'Content uploaded successfully',
                'content' => $content,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to upload content',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get content from domicile
     */
    public function getContent(Request $request, Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if (!$domicile->hasAccess($user)) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        $validated = $request->validate([
            'content_type' => 'string|in:document,image,video,audio,code,tool,other',
            'access_level' => 'string|in:private,shared,public',
            'is_public' => 'boolean',
            'search' => 'nullable|string|max:255',
            'limit' => 'integer|min:1|max:100',
            'offset' => 'integer|min:0',
        ]);

        $content = DomicileContent::search($user, $validated['search'] ?? '', [
            'content_type' => $validated['content_type'] ?? null,
            'access_level' => $validated['access_level'] ?? null,
            'is_public' => $validated['is_public'] ?? null,
        ]);

        // Apply pagination
        $limit = $validated['limit'] ?? 20;
        $offset = $validated['offset'] ?? 0;
        $paginatedContent = $content->slice($offset, $limit);

        return response()->json([
            'content' => $paginatedContent,
            'pagination' => [
                'total' => $content->count(),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $offset + $limit < $content->count(),
            ],
        ]);
    }

    /**
     * Get specific content
     */
    public function showContent(Domicile $domicile, DomicileContent $content): JsonResponse
    {
        $user = Auth::user();
        
        if (!$content->isAccessibleBy($user)) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        return response()->json([
            'content' => $content,
            'engagement_metrics' => $content->getEngagementMetrics(),
        ]);
    }

    /**
     * Delete content
     */
    public function deleteContent(Domicile $domicile, DomicileContent $content): JsonResponse
    {
        $user = Auth::user();
        
        if ($content->user_id !== $user->id) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        $content->delete();

        return response()->json([
            'message' => 'Content deleted successfully',
        ]);
    }

    /**
     * Share domicile
     */
    public function share(Request $request, Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if ($domicile->user_id !== $user->id) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        $validated = $request->validate([
            'recipient_email' => 'required|email|exists:users,email',
            'permission_level' => 'required|string|in:view,comment,download,upload,edit,admin',
            'expires_at' => 'nullable|date|after:now',
            'max_accesses' => 'integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $recipient = \App\Models\User::where('email', $validated['recipient_email'])->first();

        if (!$recipient) {
            return response()->json([
                'error' => 'User not found',
            ], 404);
        }

        try {
            $sharedAccess = $this->domicileService->shareDomicile(
                $domicile,
                $user,
                $recipient,
                $validated['permission_level'],
                $validated['expires_at'] ? \Carbon\Carbon::parse($validated['expires_at']) : null,
                $validated['max_accesses'] ?? 0,
                $validated['notes'] ?? null
            );

            return response()->json([
                'message' => 'Domicile shared successfully',
                'shared_access' => $sharedAccess,
                'access_code' => $sharedAccess->access_code,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to share domicile',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Access shared domicile
     */
    public function accessShared(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_code' => 'required|string|max:8',
        ]);

        $sharedAccess = $this->domicileService->grantAccessByCode($validated['access_code'], Auth::user());

        if (!$sharedAccess) {
            return response()->json([
                'error' => 'Invalid or expired access code',
            ], 404);
        }

        return response()->json([
            'message' => 'Access granted',
            'domicile' => $sharedAccess->domicile,
            'permissions' => $sharedAccess->getAccessStatistics(),
        ]);
    }

    /**
     * Get brand tracking data
     */
    public function getBrandTracking(Request $request, Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if (!$domicile->hasAccess($user)) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        $validated = $request->validate([
            'days' => 'integer|min:1|max:365',
            'brand_name' => 'nullable|string|max:255',
        ]);

        $brandStats = BrandTracking::getBrandStatistics(
            $validated['brand_name'] ?? '',
            now()->subDays($validated['days']),
            now()
        );

        return response()->json([
            'brand_statistics' => $brandStats,
            'trending_brands' => BrandTracking::getTrendingBrands($validated['days']),
        ]);
    }

    /**
     * Get analytics data
     */
    public function getAnalytics(Request $request, Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if (!$domicile->hasAccess($user)) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        $validated = $request->validate([
            'days' => 'integer|min:1|max:365',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $analytics = $this->domicileService->getDomicileAnalytics($domicile, $validated['days']);

        return response()->json([
            'analytics' => $analytics,
            'summary' => $domicile->getAnalyticsSummary(),
        ]);
    }

    /**
     * Get access logs
     */
    public function getAccessLogs(Request $request, Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if ($domicile->user_id !== $user->id) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        $validated = $request->validate([
            'days' => 'integer|min:1|max:365',
            'action' => 'nullable|string|in:view,download,upload,delete,share,login,logout',
            'success_only' => 'boolean',
        ]);

        $startDate = now()->subDays($validated['days']);
        $query = DomicileAccessLog::where('domicile_id', $domicile->id)
            ->where('created_at', '>=', $startDate);

        if (isset($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if ($validated['success_only'] ?? false) {
            $query->where('success', true);
        }

        $accessLogs = $query->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'access_logs' => $accessLogs,
            'statistics' => DomicileAccessLog::getAccessStatistics($domicile, $validated['days']),
        ]);
    }

    /**
     * Download content
     */
    public function downloadContent(Domicile $domicile, DomicileContent $content): JsonResponse
    {
        $user = Auth::user();
        
        if (!$content->isAccessibleBy($user)) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        if (!$content->file_path) {
            return response()->json([
                'error' => 'No file available for download',
            ], 404);
        }

        // Increment download count
        $content->incrementDownloadCount();

        // Log download
        DomicileAccessLog::logAccess($domicile, $user, 'download', $content, true);

        return response()->json([
            'message' => 'Download logged',
            'download_url' => $content->getContentUrl(),
        ]);
    }

    /**
     * Get shared access list
     */
    public function getSharedAccess(Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if ($domicile->user_id !== $user->id) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        $sharedAccess = DomicileSharedAccess::getActiveAccessForUser($user);

        return response()->json([
            'shared_access' => $sharedAccess,
            'statistics' => DomicileSharedAccess::getAccessStatisticsForDomicile($domicile),
        ]);
    }

    /**
     * Revoke shared access
     */
    public function revokeAccess(Domicile $domicile, DomicileSharedAccess $sharedAccess): JsonResponse
    {
        $user = Auth::user();
        
        if ($domicile->user_id !== $user->id) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        $sharedAccess->revoke();

        return response()->json([
            'message' => 'Access revoked successfully',
        ]);
    }

    /**
     * Get domicile statistics
     */
    public function getStatistics(Domicile $domicile): JsonResponse
    {
        $user = Auth::user();
        
        if (!$domicile->hasAccess($user)) {
            return response()->json([
                'error' => 'Access denied',
            ], 403);
        }

        return response()->json([
            'domicile' => $domicile,
            'statistics' => [
                'storage' => [
                    'used' => $domicile->storage_used,
                    'quota' => $domicile->storage_quota,
                    'available' => $domicile->getAvailableStorage(),
                    'usage_percentage' => $domicile->getStorageUsagePercentage(),
                ],
                'content' => [
                    'total_items' => $domicile->content()->count(),
                    'public_items' => $domicile->content()->where('is_public', true)->count(),
                    'total_views' => $domicile->content()->sum('view_count'),
                    'total_downloads' => $domicile->content()->sum('download_count'),
                ],
                'brand_tracking' => $domicile->getBrandTrackingSummary(),
                'analytics' => $domicile->getAnalyticsSummary(),
            ],
        ]);
    }
}
