<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DomicileAnalytics - Analytics data for personal spaces
 * 
 * Tracks user engagement, content performance, and
 * brand interaction metrics within domiciles
 */
class DomicileAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'domicile_id',
        'date',
        'page_views',
        'unique_visitors',
        'engagement_time',
        'bounce_rate',
        'content_interactions',
        'brand_mentions',
        'storage_usage',
        'bandwidth_usage',
        'popular_content',
        'user_demographics',
        'device_breakdown',
        'geographic_data',
    ];

    protected $casts = [
        'date' => 'date',
        'page_views' => 'integer',
        'unique_visitors' => 'integer',
        'engagement_time' => 'decimal:8,2',
        'bounce_rate' => 'decimal:5,2',
        'content_interactions' => 'integer',
        'brand_mentions' => 'integer',
        'storage_usage' => 'integer',
        'bandwidth_usage' => 'integer',
        'popular_content' => 'array',
        'user_demographics' => 'array',
        'device_breakdown' => 'array',
        'geographic_data' => 'array',
    ];

    /**
     * Get domicile this analytics belongs to
     */
    public function domicile(): BelongsTo
    {
        return $this->belongsTo(Domicile::class, 'domicile_id');
    }

    /**
     * Get engagement rate
     */
    public function getEngagementRate(): float
    {
        if ($this->page_views === 0) {
            return 0.0;
        }

        return ($this->content_interactions / $this->page_views) * 100.0;
    }

    /**
     * Get average time per visitor
     */
    public function getAverageTimePerVisitor(): float
    {
        if ($this->unique_visitors === 0) {
            return 0.0;
        }

        return $this->engagement_time / $this->unique_visitors;
    }

    /**
     * Get storage usage percentage
     */
    public function getStorageUsagePercentage(): float
    {
        if (!$this->domicile) {
            return 0.0;
        }

        $quota = $this->domicile->storage_quota;
        if ($quota === 0) {
            return 0.0;
        }

        return ($this->storage_usage / $quota) * 100.0;
    }

    /**
     * Get bandwidth usage formatted
     */
    public function getBandwidthUsageFormatted(): string
    {
        $bytes = $this->bandwidth_usage;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get top performing content
     */
    public function getTopPerformingContent(int $limit = 5): array
    {
        $content = $this->popular_content ?? [];
        
        // Sort by interactions and return top
        usort($content, function ($a, $b) {
            return ($b['interactions'] ?? 0) <=> ($a['interactions'] ?? 0);
        });
        
        return array_slice($content, 0, $limit);
    }

    /**
     * Get device breakdown summary
     */
    public function getDeviceBreakdownSummary(): array
    {
        $breakdown = $this->device_breakdown ?? [];
        $total = array_sum($breakdown);
        
        if ($total === 0) {
            return [];
        }

        $summary = [];
        foreach ($breakdown as $device => $count) {
            $summary[$device] = [
                'count' => $count,
                'percentage' => ($count / $total) * 100.0,
            ];
        }
        
        return $summary;
    }

    /**
     * Get geographic breakdown summary
     */
    public function getGeographicBreakdownSummary(): array
    {
        $geoData = $this->geographic_data ?? [];
        $total = array_sum(array_column($geoData, 'count'));
        
        if ($total === 0) {
            return [];
        }

        $summary = [];
        foreach ($geoData as $location => $data) {
            $summary[$location] = [
                'count' => $data['count'],
                'percentage' => ($data['count'] / $total) * 100.0,
                'country' => $data['country'] ?? 'Unknown',
            ];
        }
        
        return $summary;
    }

    /**
     * Calculate growth metrics
     */
    public function getGrowthMetrics(DomicileAnalytics $previousDay): array
    {
        if (!$previousDay) {
            return [
                'page_views_growth' => 0,
                'unique_visitors_growth' => 0,
                'engagement_growth' => 0,
                'brand_mentions_growth' => 0,
            ];
        }

        return [
            'page_views_growth' => $this->calculateGrowthRate($this->page_views, $previousDay->page_views),
            'unique_visitors_growth' => $this->calculateGrowthRate($this->unique_visitors, $previousDay->unique_visitors),
            'engagement_growth' => $this->calculateGrowthRate($this->engagement_time, $previousDay->engagement_time),
            'brand_mentions_growth' => $this->calculateGrowthRate($this->brand_mentions, $previousDay->brand_mentions),
        ];
    }

    /**
     * Calculate growth rate
     */
    private function calculateGrowthRate(float $current, float $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / $previous) * 100.0;
    }

    /**
     * Get performance score
     */
    public function getPerformanceScore(): float
    {
        $scores = [];

        // Page views score (0-30 points)
        $scores['page_views'] = min(30, $this->page_views / 10);

        // Engagement rate score (0-25 points)
        $scores['engagement_rate'] = min(25, $this->getEngagementRate() * 0.25);

        // Time on site score (0-20 points)
        $scores['engagement_time'] = min(20, $this->engagement_time / 60);

        // Low bounce rate score (0-15 points)
        $scores['bounce_rate'] = max(0, 15 - ($this->bounce_rate * 15));

        // Brand mentions score (0-10 points)
        $scores['brand_mentions'] = min(10, $this->brand_mentions);

        return array_sum($scores);
    }

    /**
     * Get analytics summary for dashboard
     */
    public function getDashboardSummary(): array
    {
        return [
            'overview' => [
                'page_views' => $this->page_views,
                'unique_visitors' => $this->unique_visitors,
                'engagement_time' => $this->engagement_time,
                'bounce_rate' => $this->bounce_rate,
                'performance_score' => $this->getPerformanceScore(),
            ],
            'content' => [
                'interactions' => $this->content_interactions,
                'top_content' => $this->getTopPerformingContent(3),
                'storage_usage' => $this->getStorageUsagePercentage(),
            ],
            'brands' => [
                'mentions' => $this->brand_mentions,
                'trending_brands' => $this->getTrendingBrands(),
            ],
            'audience' => [
                'devices' => $this->getDeviceBreakdownSummary(),
                'geography' => $this->getGeographicBreakdownSummary(),
                'demographics' => $this->user_demographics ?? [],
            ],
            'technical' => [
                'bandwidth_usage' => $this->getBandwidthUsageFormatted(),
                'date' => $this->date->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Get trending brands from analytics data
     */
    private function getTrendingBrands(): array
    {
        // This would typically query brand tracking data
        // For now, return empty array
        return [];
    }

    /**
     * Create or update analytics record
     */
    public static function recordDailyAnalytics(Domicile $domicile): self
    {
        $date = now()->toDateString();
        
        return static::updateOrCreate(
            ['domicile_id' => $domicile->id, 'date' => $date],
            [
                'page_views' => static::getPageViews($domicile, $date),
                'unique_visitors' => static::getUniqueVisitors($domicile, $date),
                'engagement_time' => static::getEngagementTime($domicile, $date),
                'bounce_rate' => static::getBounceRate($domicile, $date),
                'content_interactions' => static::getContentInteractions($domicile, $date),
                'brand_mentions' => static::getBrandMentions($domicile, $date),
                'storage_usage' => $domicile->storage_used,
                'bandwidth_usage' => static::getBandwidthUsage($domicile, $date),
                'popular_content' => static::getPopularContent($domicile, $date),
                'user_demographics' => static::getUserDemographics($domicile, $date),
                'device_breakdown' => static::getDeviceBreakdown($domicile, $date),
                'geographic_data' => static::getGeographicData($domicile, $date),
            ]
        );
    }

    /**
     * Get page views for date
     */
    private static function getPageViews(Domicile $domicile, string $date): int
    {
        return DomicileAccessLog::where('domicile_id', $domicile->id)
            ->whereDate('created_at', $date)
            ->where('action', 'view')
            ->where('success', true)
            ->count();
    }

    /**
     * Get unique visitors for date
     */
    private static function getUniqueVisitors(Domicile $domicile, string $date): int
    {
        return DomicileAccessLog::where('domicile_id', $domicile->id)
            ->whereDate('created_at', $date)
            ->where('success', true)
            ->distinct('user_id')
            ->count();
    }

    /**
     * Get engagement time for date
     */
    private static function getEngagementTime(Domicile $domicile, string $date): float
    {
        // Simplified calculation - would need session tracking
        return 180.0; // 3 minutes average
    }

    /**
     * Get bounce rate for date
     */
    private static function getBounceRate(Domicile $domicile, string $date): float
    {
        // Simplified calculation - would need session tracking
        return 0.35; // 35% bounce rate
    }

    /**
     * Get content interactions for date
     */
    private static function getContentInteractions(Domicile $domicile, string $date): int
    {
        return DomicileAccessLog::where('domicile_id', $domicile->id)
            ->whereDate('created_at', $date)
            ->whereIn('action', ['download', 'share', 'comment'])
            ->where('success', true)
            ->count();
    }

    /**
     * Get brand mentions for date
     */
    private static function getBrandMentions(Domicile $domicile, string $date): int
    {
        return BrandTracking::where('domicile_id', $domicile->id)
            ->whereDate('created_at', $date)
            ->count();
    }

    /**
     * Get bandwidth usage for date
     */
    private static function getBandwidthUsage(Domicile $domicile, string $date): int
    {
        // Simplified calculation based on downloads
        return DomicileAccessLog::where('domicile_id', $domicile->id)
            ->whereDate('created_at', $date)
            ->where('action', 'download')
            ->where('success', true)
            ->count() * 1024 * 1024; // Assume 1MB per download
    }

    /**
     * Get popular content for date
     */
    private static function getPopularContent(Domicile $domicile, string $date): array
    {
        return DomicileContent::where('domicile_id', $domicile->id)
            ->whereDate('created_at', $date)
            ->orderBy('view_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($content) {
                return [
                    'id' => $content->id,
                    'title' => $content->title,
                    'type' => $content->content_type,
                    'views' => $content->view_count,
                    'interactions' => $content->view_count + $content->download_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get user demographics for date
     */
    private static function getUserDemographics(Domicile $domicile, string $date): array
    {
        // Simplified demographics - would need user profile data
        return [
            'age_groups' => [
                '18-24' => 25,
                '25-34' => 35,
                '35-44' => 25,
                '45-54' => 10,
                '55+' => 5,
            ],
            'genders' => [
                'male' => 55,
                'female' => 40,
                'other' => 5,
            ],
        ];
    }

    /**
     * Get device breakdown for date
     */
    private static function getDeviceBreakdown(Domicile $domicile, string $date): array
    {
        // Simplified device breakdown - would parse user agents
        return [
            'Desktop' => 60,
            'Mobile' => 35,
            'Tablet' => 5,
        ];
    }

    /**
     * Get geographic data for date
     */
    private static function getGeographicData(Domicile $domicile, string $date): array
    {
        // Simplified geographic data - would use IP geolocation
        return [
            'United States' => ['count' => 40, 'country' => 'US'],
            'Canada' => ['count' => 20, 'country' => 'CA'],
            'United Kingdom' => ['count' => 15, 'country' => 'GB'],
            'Germany' => ['count' => 10, 'country' => 'DE'],
            'Other' => ['count' => 15, 'country' => 'Other'],
        ];
    }
}
