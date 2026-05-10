<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * DomicileContent - Content stored in user's personal space
 * 
 * Represents any content item (documents, media, code, etc.) 
 * stored within a user's encrypted domicile
 */
class DomicileContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'domicile_id',
        'user_id',
        'title',
        'description',
        'content_type',
        'content_data',
        'file_path',
        'file_size',
        'encryption_status',
        'access_level',
        'tags',
        'metadata',
        'is_public',
        'view_count',
        'download_count',
        'reputation_score',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'is_public' => 'boolean',
        'content_data' => 'encrypted',
        'encryption_status' => 'string',
        'view_count' => 'integer',
        'download_count' => 'integer',
        'reputation_score' => 'decimal:8,4',
    ];

    /**
     * Get domicile this content belongs to
     */
    public function domicile(): BelongsTo
    {
        return $this->belongsTo(Domicile::class, 'domicile_id');
    }

    /**
     * Get user who created this content
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get brand tracking entries for this content
     */
    public function brandTracking(): MorphMany
    {
        return $this->morphMany(BrandTracking::class, 'trackable');
    }

    /**
     * Get access logs for this content
     */
    public function accessLogs(): MorphMany
    {
        return $this->morphMany(DomicileAccessLog::class, 'target');
    }

    /**
     * Get reputation transactions for this content
     */
    public function reputationTransactions(): MorphMany
    {
        return $this->morphMany(ReputationTransaction::class, 'target');
    }

    /**
     * Check if content is encrypted
     */
    public function isEncrypted(): bool
    {
        return $this->encryption_status === 'encrypted';
    }

    /**
     * Check if content is accessible by user
     */
    public function isAccessibleBy(User $user): bool
    {
        // Owner has full access
        if ($this->user_id === $user->id) {
            return true;
        }

        // Public content is accessible to all
        if ($this->is_public) {
            return true;
        }

        // Check domicile access
        return $this->domicile->hasAccess($user);
    }

    /**
     * Get content type display name
     */
    public function getContentTypeDisplayName(): string
    {
        return match($this->content_type) {
            'document' => 'Document',
            'image' => 'Image',
            'video' => 'Video',
            'audio' => 'Audio',
            'code' => 'Code Snippet',
            'tool' => 'Tool',
            'other' => 'Other',
            default => 'Unknown'
        };
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeFormatted(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Increment view count
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Increment download count
     */
    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    /**
     * Update reputation score
     */
    public function updateReputationScore(float $score): void
    {
        $this->reputation_score = $score;
        $this->save();
    }

    /**
     * Get engagement metrics
     */
    public function getEngagementMetrics(): array
    {
        return [
            'views' => $this->view_count,
            'downloads' => $this->download_count,
            'reputation' => $this->reputation_score,
            'engagement_rate' => $this->calculateEngagementRate(),
        ];
    }

    /**
     * Calculate engagement rate
     */
    private function calculateEngagementRate(): float
    {
        if ($this->view_count === 0) {
            return 0.0;
        }

        return ($this->download_count / $this->view_count) * 100.0;
    }

    /**
     * Get content URL
     */
    public function getContentUrl(): string
    {
        if ($this->file_path) {
            return route('domicile.content.download', [
                'domicile' => $this->domicile_id,
                'content' => $this->id
            ]);
        }

        return route('domicile.content.view', [
            'domicile' => $this->domicile_id,
            'content' => $this->id
        ]);
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrl(): ?string
    {
        if ($this->content_type === 'image') {
            return route('domicile.content.thumbnail', [
                'domicile' => $this->domicile_id,
                'content' => $this->id
            ]);
        }

        return null;
    }

    /**
     * Search content by tags and metadata
     */
    public static function search(User $user, string $query, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $queryBuilder = static::with(['domicile'])
            ->where('user_id', $user->id)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhereJsonContains('tags', $query)
                  ->orWhereJsonContains('metadata', $query);
            });

        // Apply filters
        if (isset($filters['content_type'])) {
            $queryBuilder->where('content_type', $filters['content_type']);
        }

        if (isset($filters['access_level'])) {
            $queryBuilder->where('access_level', $filters['access_level']);
        }

        if (isset($filters['is_public'])) {
            $queryBuilder->where('is_public', $filters['is_public']);
        }

        return $queryBuilder->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get popular content in user's domicile
     */
    public static function getPopularContent(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['domicile'])
            ->where('user_id', $user->id)
            ->orderBy('view_count', 'desc')
            ->orderBy('download_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get content by type
     */
    public static function getByType(User $user, string $contentType): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['domicile'])
            ->where('user_id', $user->id)
            ->where('content_type', $contentType)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
