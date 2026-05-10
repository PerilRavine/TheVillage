<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Domicile - Personal encrypted space for users
 * 
 * Represents a user's personal space within The Village where they can
 * store private content, manage brand tracking, and control access
 */
class Domicile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'theme',
        'privacy_settings',
        'encryption_key_id',
        'storage_quota',
        'storage_used',
        'is_public',
        'access_code',
        'brand_tracking_enabled',
        'analytics_enabled',
    ];

    protected $casts = [
        'privacy_settings' => 'array',
        'is_public' => 'boolean',
        'brand_tracking_enabled' => 'boolean',
        'analytics_enabled' => 'boolean',
        'storage_quota' => 'integer',
        'storage_used' => 'integer',
    ];

    /**
     * Get the owner of this domicile
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the encryption key for this domicile
     */
    public function encryptionKey(): BelongsTo
    {
        return $this->belongsTo(EncryptionKey::class, 'encryption_key_id');
    }

    /**
     * Get all content items in this domicile
     */
    public function content(): HasMany
    {
        return $this->hasMany(DomicileContent::class, 'domicile_id');
    }

    /**
     * Get brand tracking data for this domicile
     */
    public function brandTracking(): HasMany
    {
        return $this->hasMany(BrandTracking::class, 'domicile_id');
    }

    /**
     * Get access logs for this domicile
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(DomicileAccessLog::class, 'domicile_id');
    }

    /**
     * Get shared access permissions
     */
    public function sharedAccess(): HasMany
    {
        return $this->hasMany(DomicileSharedAccess::class, 'domicile_id');
    }

    /**
     * Get analytics data for this domicile
     */
    public function analytics(): HasMany
    {
        return $this->hasMany(DomicileAnalytics::class, 'domicile_id');
    }

    /**
     * Check if user has access to this domicile
     */
    public function hasAccess(User $user): bool
    {
        // Owner has full access
        if ($this->user_id === $user->id) {
            return true;
        }

        // Check public access
        if ($this->is_public) {
            return true;
        }

        // Check shared access
        return $this->sharedAccess()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Get storage usage percentage
     */
    public function getStorageUsagePercentage(): float
    {
        if ($this->storage_quota === 0) {
            return 0.0;
        }

        return ($this->storage_used / $this->storage_quota) * 100.0;
    }

    /**
     * Check if storage quota is exceeded
     */
    public function isStorageQuotaExceeded(): bool
    {
        return $this->storage_used > $this->storage_quota;
    }

    /**
     * Get available storage space
     */
    public function getAvailableStorage(): int
    {
        return max(0, $this->storage_quota - $this->storage_used);
    }

    /**
     * Log access to domicile
     */
    public function logAccess(User $user, string $action, ?string $details = null): void
    {
        $this->accessLogs()->create([
            'user_id' => $user->id,
            'action' => $action,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Update storage usage
     */
    public function updateStorageUsage(int $sizeChange): void
    {
        $this->storage_used = max(0, $this->storage_used + $sizeChange);
        $this->save();
    }

    /**
     * Get brand tracking summary
     */
    public function getBrandTrackingSummary(): array
    {
        return $this->brandTracking()
            ->selectRaw('
                brand_name,
                COUNT(*) as mentions,
                COUNT(DISTINCT user_id) as unique_users,
                MAX(created_at) as last_mention
            ')
            ->groupBy('brand_name')
            ->orderBy('mentions', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get analytics summary for the last 30 days
     */
    public function getAnalyticsSummary(): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        return $this->analytics()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('
                DATE(created_at) as date,
                SUM(page_views) as page_views,
                SUM(unique_visitors) as unique_visitors,
                AVG(engagement_time) as avg_engagement_time
            ')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();
    }
}
