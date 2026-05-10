<?php

namespace App\Services;

use App\Models\Domicile;
use App\Models\DomicileContent;
use App\Models\BrandTracking;
use App\Models\DomicileAccessLog;
use App\Models\DomicileAnalytics;
use App\Models\DomicileSharedAccess;
use App\Models\User;
use App\Models\ReputationTransaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * DomicileService - Manage personal encrypted spaces
 * 
 * Handles creation, management, and analytics for user domiciles
 * with encryption, access control, and brand tracking
 */
class DomicileService
{
    /**
     * Create a new domicile for user
     */
    public function createDomicile(User $user, array $data): Domicile
    {
        $domicile = Domicile::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'theme' => $data['theme'] ?? 'default',
            'privacy_settings' => $data['privacy_settings'] ?? [],
            'storage_quota' => $data['storage_quota'] ?? 1073741824, // 1GB default
            'is_public' => $data['is_public'] ?? false,
            'brand_tracking_enabled' => $data['brand_tracking_enabled'] ?? true,
            'analytics_enabled' => $data['analytics_enabled'] ?? true,
        ]);

        // Log creation
        DomicileAccessLog::logAccess($domicile, $user, 'create', null, true);

        return $domicile;
    }

    /**
     * Upload content to domicile
     */
    public function uploadContent(Domicile $domicile, User $user, array $contentData): DomicileContent
    {
        // Check storage quota
        if ($domicile->isStorageQuotaExceeded()) {
            throw new \Exception('Storage quota exceeded');
        }

        // Handle file upload
        $filePath = null;
        $fileSize = 0;
        if (isset($contentData['file'])) {
            $file = $contentData['file'];
            $filePath = $file->store('domicile/' . $domicile->id, 'private');
            $fileSize = $file->getSize();
        }

        // Encrypt content data if needed
        $contentDataToStore = $contentData['content'] ?? null;
        $encryptionStatus = 'unencrypted';
        
        if ($domicile->privacy_settings['encrypt_content'] ?? false) {
            $contentDataToStore = Crypt::encrypt($contentDataToStore);
            $encryptionStatus = 'encrypted';
        }

        $content = DomicileContent::create([
            'domicile_id' => $domicile->id,
            'user_id' => $user->id,
            'title' => $contentData['title'],
            'description' => $contentData['description'] ?? null,
            'content_type' => $contentData['content_type'],
            'content_data' => $contentDataToStore,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'encryption_status' => $encryptionStatus,
            'access_level' => $contentData['access_level'] ?? 'private',
            'tags' => $contentData['tags'] ?? [],
            'metadata' => $contentData['metadata'] ?? [],
            'is_public' => $contentData['is_public'] ?? false,
        ]);

        // Update storage usage
        $domicile->updateStorageUsage($fileSize);

        // Track brands in content
        $this->trackBrandsInContent($content, $user);

        // Log upload
        DomicileAccessLog::logAccess($domicile, $user, 'upload', $content, true);

        return $content;
    }

    /**
     * Share domicile with another user
     */
    public function shareDomicile(
        Domicile $domicile,
        User $owner,
        User $recipient,
        string $permissionLevel,
        ?\Carbon\Carbon $expiresAt = null,
        int $maxAccesses = 0,
        ?string $notes = null
    ): DomicileSharedAccess {
        
        $sharedAccess = DomicileSharedAccess::createSharedAccess(
            $domicile,
            $recipient,
            $owner,
            $permissionLevel,
            $expiresAt,
            $maxAccesses,
            $notes
        );

        // Log sharing
        DomicileAccessLog::logAccess($domicile, $owner, 'share', $sharedAccess, true);

        return $sharedAccess;
    }

    /**
     * Grant access via access code
     */
    public function grantAccessByCode(
        Domicile $domicile,
        string $accessCode,
        User $user
    ): ?DomicileSharedAccess {
        
        $sharedAccess = DomicileSharedAccess::findByAccessCode($accessCode);
        
        if (!$sharedAccess || !$sharedAccess->isValid()) {
            DomicileAccessLog::logAccess($domicile, $user, 'access_denied', null, false, 'Invalid or expired access code');
            return null;
        }

        // Increment access count
        $sharedAccess->incrementAccessCount();

        // Log successful access
        DomicileAccessLog::logAccess($domicile, $user, 'access_granted', $sharedAccess, true);

        return $sharedAccess;
    }

    /**
     * Track brands in content
     */
    private function trackBrandsInContent(DomicileContent $content, User $user): void
    {
        $text = $content->title . ' ' . ($content->description ?? '');
        $brands = $this->extractBrands($text);

        foreach ($brands as $brand) {
            BrandTracking::createFromContent($content, $brand);
        }

        // Update reputation based on brand mentions
        if (count($brands) > 0) {
            $this->updateReputationForBrandMentions($user, $brands);
        }
    }

    /**
     * Extract brands from text
     */
    private function extractBrands(string $text): array
    {
        // Simplified brand extraction - would use NLP in production
        $knownBrands = [
            'Apple', 'Google', 'Microsoft', 'Amazon', 'Tesla', 'Netflix',
            'Facebook', 'Twitter', 'Instagram', 'LinkedIn', 'TikTok',
            'Adobe', 'Salesforce', 'Oracle', 'IBM', 'Intel',
        ];

        $foundBrands = [];
        foreach ($knownBrands as $brand) {
            if (stripos($text, $brand) !== false) {
                $foundBrands[] = $brand;
            }
        }

        return array_unique($foundBrands);
    }

    /**
     * Update reputation for brand mentions
     */
    private function updateReputationForBrandMentions(User $user, array $brands): void
    {
        $totalBonus = 0;
        
        foreach ($brands as $brand) {
            // Positive brand mentions give small reputation bonus
            $totalBonus += 0.5;
        }

        if ($totalBonus > 0) {
            ReputationTransaction::create([
                'user_id' => $user->id,
                'transaction_type' => 'brand_mention',
                'amount' => $totalBonus,
                'context' => 'domicile_content',
                'description' => 'Brand mentions in content: ' . implode(', ', $brands),
                'metadata' => [
                    'brands' => $brands,
                    'mention_count' => count($brands),
                ],
            ]);
        }
    }

    /**
     * Get domicile analytics
     */
    public function getDomicileAnalytics(Domicile $domicile, int $days = 30): array
    {
        // Record daily analytics
        DomicileAnalytics::recordDailyAnalytics($domicile);

        return [
            'overview' => $this->getOverviewStats($domicile, $days),
            'content_performance' => $this->getContentPerformance($domicile, $days),
            'brand_tracking' => $this->getBrandTrackingStats($domicile, $days),
            'access_patterns' => $this->getAccessPatterns($domicile, $days),
            'storage_usage' => $this->getStorageUsageStats($domicile),
        ];
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats(Domicile $domicile, int $days): array
    {
        $startDate = now()->subDays($days);
        
        $totalViews = DomicileContent::where('domicile_id', $domicile->id)
            ->where('created_at', '>=', $startDate)
            ->sum('view_count');

        $totalDownloads = DomicileContent::where('domicile_id', $domicile->id)
            ->where('created_at', '>=', $startDate)
            ->sum('download_count');

        $uniqueVisitors = DomicileAccessLog::where('domicile_id', $domicile->id)
            ->where('created_at', '>=', $startDate)
            ->distinct('user_id')
            ->count();

        return [
            'total_views' => $totalViews,
            'total_downloads' => $totalDownloads,
            'unique_visitors' => $uniqueVisitors,
            'storage_usage_percentage' => $domicile->getStorageUsagePercentage(),
            'storage_quota' => $domicile->storage_quota,
            'storage_used' => $domicile->storage_used,
        ];
    }

    /**
     * Get content performance metrics
     */
    private function getContentPerformance(Domicile $domicile, int $days): array
    {
        $startDate = now()->subDays($days);
        
        $topContent = DomicileContent::where('domicile_id', $domicile->id)
            ->where('created_at', '>=', $startDate)
            ->orderByRaw('(view_count + download_count) DESC')
            ->limit(10)
            ->get();

        $contentTypeStats = DomicileContent::where('domicile_id', $domicile->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('content_type, COUNT(*) as count, SUM(view_count) as total_views, SUM(download_count) as total_downloads')
            ->groupBy('content_type')
            ->get();

        return [
            'top_content' => $topContent->map(function ($content) {
                return [
                    'id' => $content->id,
                    'title' => $content->title,
                    'type' => $content->content_type,
                    'views' => $content->view_count,
                    'downloads' => $content->download_count,
                    'reputation_score' => $content->reputation_score,
                ];
            }),
            'content_type_stats' => $contentTypeStats->toArray(),
        ];
    }

    /**
     * Get brand tracking statistics
     */
    private function getBrandTrackingStats(Domicile $domicile, int $days): array
    {
        $startDate = now()->subDays($days);
        
        $brandStats = BrandTracking::where('domicile_id', $domicile->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                brand_name,
                COUNT(*) as mentions,
                AVG(sentiment_score) as avg_sentiment,
                COUNT(DISTINCT user_id) as unique_users,
                SUM(CASE WHEN is_positive = 1 THEN 1 ELSE 0 END) as positive_count,
                SUM(CASE WHEN is_negative = 1 THEN 1 ELSE 0 END) as negative_count
            ')
            ->groupBy('brand_name')
            ->orderBy('mentions', 'desc')
            ->limit(10)
            ->get();

        $trendingBrands = BrandTracking::getTrendingBrands($days, 5);

        return [
            'brand_mentions' => $brandStats->toArray(),
            'trending_brands' => $trendingBrands->toArray(),
            'sentiment_breakdown' => [
                'positive' => $brandStats->where('is_positive', true)->sum('mentions'),
                'negative' => $brandStats->where('is_negative', true)->sum('mentions'),
                'neutral' => $brandStats->where('is_neutral', true)->sum('mentions'),
            ],
        ];
    }

    /**
     * Get access patterns
     */
    private function getAccessPatterns(Domicile $domicile, int $days): array
    {
        $startDate = now()->subDays($days);
        
        $accessLogs = DomicileAccessLog::where('domicile_id', $domicile->id)
            ->where('created_at', '>=', $startDate)
            ->get();

        $hourlyAccess = $accessLogs->groupBy(function ($log) {
            return $log->created_at->format('H');
        })->map->count();

        $dailyAccess = $accessLogs->groupBy(function ($log) {
            return $log->created_at->format('Y-m-d');
        })->map->count();

        return [
            'total_accesses' => $accessLogs->count(),
            'unique_users' => $accessLogs->pluck('user_id')->unique()->count(),
            'hourly_pattern' => $hourlyAccess->toArray(),
            'daily_pattern' => $dailyAccess->toArray(),
            'failed_attempts' => $accessLogs->where('success', false)->count(),
        ];
    }

    /**
     * Get storage usage statistics
     */
    private function getStorageUsageStats(Domicile $domicile): array
    {
        $contentByType = DomicileContent::where('domicile_id', $domicile->id)
            ->selectRaw('content_type, COUNT(*) as count, SUM(file_size) as total_size')
            ->groupBy('content_type')
            ->get();

        return [
            'storage_quota' => $domicile->storage_quota,
            'storage_used' => $domicile->storage_used,
            'storage_available' => $domicile->getAvailableStorage(),
            'usage_percentage' => $domicile->getStorageUsagePercentage(),
            'content_by_type' => $contentByType->toArray(),
        ];
    }

    /**
     * Cleanup expired shared access
     */
    public function cleanupExpiredAccess(): int
    {
        return DomicileSharedAccess::cleanupExpiredAccess();
    }

    /**
     * Generate access code for domicile
     */
    public function generateAccessCode(Domicile $domicile): string
    {
        return strtoupper(substr(md5($domicile->id . now()->timestamp), 0, 8));
    }

    /**
     * Validate domicile access
     */
    public function validateAccess(Domicile $domicile, User $user): bool
    {
        // Check if user has access
        if (!$domicile->hasAccess($user)) {
            DomicileAccessLog::logAccess($domicile, $user, 'access_denied', null, false, 'No access permission');
            return false;
        }

        // Log successful access
        DomicileAccessLog::logAccess($domicile, $user, 'view', null, true);
        return true;
    }

    /**
     * Get user's domiciles with statistics
     */
    public function getUserDomiciles(User $user): array
    {
        $domiciles = Domicile::where('user_id', $user->id)
            ->with(['content', 'sharedAccess'])
            ->get();

        return $domiciles->map(function ($domicile) {
            return [
                'id' => $domicile->id,
                'name' => $domicile->name,
                'theme' => $domicile->theme,
                'storage_usage' => $domicile->getStorageUsagePercentage(),
                'content_count' => $domicile->content->count(),
                'shared_access_count' => $domicile->sharedAccess->where('is_active', true)->count(),
                'created_at' => $domicile->created_at,
                'updated_at' => $domicile->updated_at,
            ];
        })->toArray();
    }

    /**
     * Search content across user's domiciles
     */
    public function searchContent(User $user, string $query, array $filters = []): array
    {
        $domiciles = Domicile::where('user_id', $user->id)->pluck('id');
        
        $content = DomicileContent::whereIn('domicile_id', $domiciles)
            ->with(['domicile'])
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhereJsonContains('tags', $query)
                  ->orWhereJsonContains('metadata', $query);
            });

        // Apply filters
        if (isset($filters['content_type'])) {
            $content->where('content_type', $filters['content_type']);
        }

        if (isset($filters['access_level'])) {
            $content->where('access_level', $filters['access_level']);
        }

        return $content->orderBy('created_at', 'desc')->get()->toArray();
    }
}
