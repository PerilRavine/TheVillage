<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * BrandTracking - Track brand mentions and interactions
 * 
 * Monitors brand mentions across user content and provides
 * analytics for brand engagement and reputation tracking
 */
class BrandTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'domicile_id',
        'user_id',
        'brand_name',
        'trackable_type',
        'trackable_id',
        'mention_type',
        'sentiment_score',
        'context',
        'metadata',
        'is_positive',
        'is_negative',
        'is_neutral',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_positive' => 'boolean',
        'is_negative' => 'boolean',
        'is_neutral' => 'boolean',
        'sentiment_score' => 'decimal:4,2',
    ];

    /**
     * Get domicile where this tracking occurred
     */
    public function domicile(): BelongsTo
    {
        return $this->belongsTo(Domicile::class, 'domicile_id');
    }

    /**
     * Get user who made the mention
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the trackable model (polymorphic)
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get sentiment category
     */
    public function getSentimentCategory(): string
    {
        if ($this->sentiment_score >= 0.6) {
            return 'positive';
        } elseif ($this->sentiment_score <= -0.6) {
            return 'negative';
        }
        
        return 'neutral';
    }

    /**
     * Get sentiment emoji
     */
    public function getSentimentEmoji(): string
    {
        return match($this->getSentimentCategory()) {
            'positive' => '😊',
            'negative' => '😞',
            'neutral' => '😐',
            default => '📊'
        };
    }

    /**
     * Check if mention is positive
     */
    public function isPositive(): bool
    {
        return $this->sentiment_score >= 0.6;
    }

    /**
     * Check if mention is negative
     */
    public function isNegative(): bool
    {
        return $this->sentiment_score <= -0.6;
    }

    /**
     * Check if mention is neutral
     */
    public function isNeutral(): bool
    {
        return $this->sentiment_score > -0.6 && $this->sentiment_score < 0.6;
    }

    /**
     * Get brand mention statistics
     */
    public static function getBrandStatistics(string $brandName, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null): array
    {
        $query = static::where('brand_name', $brandName);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $mentions = $query->get();

        return [
            'total_mentions' => $mentions->count(),
            'positive_mentions' => $mentions->where('is_positive', true)->count(),
            'negative_mentions' => $mentions->where('is_negative', true)->count(),
            'neutral_mentions' => $mentions->where('is_neutral', true)->count(),
            'average_sentiment' => $mentions->avg('sentiment_score'),
            'unique_users' => $mentions->pluck('user_id')->unique()->count(),
            'top_mentioners' => $mentions->groupBy('user_id')
                ->map->count()
                ->sortDesc()
                ->take(5)
                ->map(function ($count, $userId) {
                    $user = User::find($userId);
                    return [
                        'user' => $user?->username,
                        'count' => $count,
                    ];
                })
                ->values(),
        ];
    }

    /**
     * Get trending brands
     */
    public static function getTrendingBrands(int $days = 7, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('
                brand_name,
                COUNT(*) as mention_count,
                AVG(sentiment_score) as avg_sentiment,
                COUNT(DISTINCT user_id) as unique_users
            ')
            ->groupBy('brand_name')
            ->orderBy('mention_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get brand sentiment over time
     */
    public static function getBrandSentimentOverTime(string $brandName, int $days = 30): array
    {
        return static::where('brand_name', $brandName)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as mentions,
                AVG(sentiment_score) as avg_sentiment,
                SUM(CASE WHEN sentiment_score >= 0.6 THEN 1 ELSE 0 END) as positive_count,
                SUM(CASE WHEN sentiment_score <= -0.6 THEN 1 ELSE 0 END) as negative_count
            ')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Create brand mention from content
     */
    public static function createFromContent(Model $content, string $brandName, string $mentionType = 'explicit'): self
    {
        // Analyze sentiment (simplified version)
        $sentimentScore = static::analyzeSentiment($content->title . ' ' . $content->description);
        
        return static::create([
            'domicile_id' => $content->domicile_id ?? null,
            'user_id' => $content->user_id,
            'brand_name' => $brandName,
            'trackable_type' => get_class($content),
            'trackable_id' => $content->id,
            'mention_type' => $mentionType,
            'sentiment_score' => $sentimentScore,
            'context' => 'content_mention',
            'is_positive' => $sentimentScore >= 0.6,
            'is_negative' => $sentimentScore <= -0.6,
            'is_neutral' => $sentimentScore > -0.6 && $sentimentScore < 0.6,
            'metadata' => [
                'content_title' => $content->title,
                'content_type' => $content->content_type ?? 'unknown',
                'created_at' => $content->created_at,
            ],
        ]);
    }

    /**
     * Simple sentiment analysis
     */
    private static function analyzeSentiment(string $text): float
    {
        // Simplified sentiment analysis
        $positiveWords = ['good', 'great', 'excellent', 'amazing', 'love', 'best', 'awesome', 'fantastic'];
        $negativeWords = ['bad', 'terrible', 'awful', 'hate', 'worst', 'horrible', 'disgusting'];
        
        $words = str_word_count(strtolower($text));
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            if (str_contains($text, $word)) {
                $positiveCount++;
            }
        }
        
        foreach ($negativeWords as $word) {
            if (str_contains($text, $word)) {
                $negativeCount++;
            }
        }
        
        if ($words === 0) {
            return 0.0;
        }
        
        // Normalize between -1 and 1
        $rawScore = ($positiveCount - $negativeCount) / $words;
        return max(-1.0, min(1.0, $rawScore));
    }

    /**
     * Get brand reputation impact
     */
    public function getReputationImpact(): array
    {
        return [
            'brand_name' => $this->brand_name,
            'sentiment' => $this->getSentimentCategory(),
            'sentiment_score' => $this->sentiment_score,
            'impact_level' => $this->calculateImpactLevel(),
            'user_reputation_bonus' => $this->calculateReputationBonus(),
        ];
    }

    /**
     * Calculate impact level
     */
    private function calculateImpactLevel(): string
    {
        $absScore = abs($this->sentiment_score);
        
        if ($absScore >= 0.8) {
            return 'high';
        } elseif ($absScore >= 0.4) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Calculate reputation bonus/penalty
     */
    private function calculateReputationBonus(): float
    {
        if ($this->isPositive()) {
            return 2.0; // Positive mentions give reputation bonus
        } elseif ($this->isNegative()) {
            return -1.0; // Negative mentions give reputation penalty
        }
        
        return 0.0; // Neutral mentions have no impact
    }
}
