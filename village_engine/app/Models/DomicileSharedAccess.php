<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DomicileSharedAccess - Control access to personal spaces
 * 
 * Manages shared access permissions for user domiciles
 * with time-based access codes and permission levels
 */
class DomicileSharedAccess extends Model
{
    use HasFactory;

    protected $fillable = [
        'domicile_id',
        'user_id',
        'access_code',
        'permission_level',
        'expires_at',
        'granted_by_user_id',
        'access_count',
        'max_accesses',
        'is_active',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'access_count' => 'integer',
        'max_accesses' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get domicile that access is for
     */
    public function domicile(): BelongsTo
    {
        return $this->belongsTo(Domicile::class, 'domicile_id');
    }

    /**
     * Get user who has access
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get user who granted access
     */
    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /**
     * Check if access is still valid
     */
    public function isValid(): bool
    {
        return $this->is_active && 
               $this->expires_at && 
               $this->expires_at->isFuture() &&
               ($this->max_accesses === 0 || $this->access_count < $this->max_accesses);
    }

    /**
     * Check if access is expired
     */
    public function isExpired(): bool
    {
        return !$this->expires_at || $this->expires_at->isPast();
    }

    /**
     * Check if access limit reached
     */
    public function isAccessLimitReached(): bool
    {
        return $this->max_accesses > 0 && $this->access_count >= $this->max_accesses;
    }

    /**
     * Get permission level display name
     */
    public function getPermissionLevelDisplayName(): string
    {
        return match($this->permission_level) {
            'view' => 'View Only',
            'comment' => 'View & Comment',
            'download' => 'View, Comment & Download',
            'upload' => 'View, Comment, Download & Upload',
            'edit' => 'Full Edit Access',
            'admin' => 'Administrator Access',
            default => 'Unknown'
        };
    }

    /**
     * Get access status display
     */
    public function getAccessStatusDisplay(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        if ($this->isAccessLimitReached()) {
            return 'Limit Reached';
        }

        return 'Active';
    }

    /**
     * Get time remaining until expiration
     */
    public function getTimeRemaining(): string
    {
        if (!$this->expires_at) {
            return 'Never';
        }

        if ($this->expires_at->isPast()) {
            return 'Expired';
        }

        $diff = $this->expires_at->diffForHumans(now());
        return $diff;
    }

    /**
     * Get access usage percentage
     */
    public function getAccessUsagePercentage(): float
    {
        if ($this->max_accesses === 0) {
            return 0.0;
        }

        return ($this->access_count / $this->max_accesses) * 100.0;
    }

    /**
     * Increment access count
     */
    public function incrementAccessCount(): void
    {
        $this->increment('access_count');
        
        // Deactivate if limit reached
        if ($this->isAccessLimitReached()) {
            $this->update(['is_active' => false]);
        }
    }

    /**
     * Revoke access
     */
    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Extend access
     */
    public function extend(\Carbon\Carbon $newExpiration, int $additionalAccesses = 0): void
    {
        $this->update([
            'expires_at' => $newExpiration,
            'max_accesses' => $this->max_accesses + $additionalAccesses,
            'is_active' => true,
        ]);
    }

    /**
     * Get access statistics
     */
    public function getAccessStatistics(): array
    {
        return [
            'total_accesses' => $this->access_count,
            'max_accesses' => $this->max_accesses,
            'usage_percentage' => $this->getAccessUsagePercentage(),
            'time_remaining' => $this->getTimeRemaining(),
            'status' => $this->getAccessStatusDisplay(),
            'permission_level' => $this->getPermissionLevelDisplayName(),
        ];
    }

    /**
     * Create new shared access
     */
    public static function createSharedAccess(
        Domicile $domicile,
        User $user,
        User $grantedBy,
        string $permissionLevel,
        ?\Carbon\Carbon $expiresAt = null,
        int $maxAccesses = 0,
        ?string $notes = null
    ): self {
        
        $accessCode = static::generateAccessCode();
        
        return static::create([
            'domicile_id' => $domicile->id,
            'user_id' => $user->id,
            'access_code' => $accessCode,
            'permission_level' => $permissionLevel,
            'expires_at' => $expiresAt ?? now()->addDays(7),
            'granted_by_user_id' => $grantedBy->id,
            'access_count' => 0,
            'max_accesses' => $maxAccesses,
            'is_active' => true,
            'notes' => $notes,
            'metadata' => [
                'created_at' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        ]);
    }

    /**
     * Generate unique access code
     */
    private static function generateAccessCode(): string
    {
        return strtoupper(substr(md5(uniqid()), 0, 8));
    }

    /**
     * Find by access code
     */
    public static function findByAccessCode(string $accessCode): ?self
    {
        return static::where('access_code', $accessCode)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Get active shared access for user
     */
    public static function getActiveAccessForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['domicile', 'grantedByUser'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->get();
    }

    /**
     * Get expired access that needs cleanup
     */
    public static function getExpiredAccess(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('expires_at', '<=', now())
            ->where('is_active', true)
            ->get();
    }

    /**
     * Cleanup expired access
     */
    public static function cleanupExpiredAccess(): int
    {
        $expiredAccess = static::getExpiredAccess();
        
        $count = $expiredAccess->count();
        
        foreach ($expiredAccess as $access) {
            $access->revoke();
        }
        
        return $count;
    }

    /**
     * Get access by permission level
     */
    public static function getByPermissionLevel(Domicile $domicile, string $permissionLevel): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['user'])
            ->where('domicile_id', $domicile->id)
            ->where('permission_level', $permissionLevel)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->get();
    }

    /**
     * Get access statistics for domicile
     */
    public static function getAccessStatisticsForDomicile(Domicile $domicile): array
    {
        $activeAccess = static::where('domicile_id', $domicile->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->get();

        $expiredAccess = static::where('domicile_id', $domicile->id)
            ->where('expires_at', '<=', now())
            ->get();

        return [
            'total_shared' => $activeAccess->count() + $expiredAccess->count(),
            'active_accesses' => $activeAccess->count(),
            'expired_accesses' => $expiredAccess->count(),
            'by_permission_level' => $activeAccess->groupBy('permission_level')
                ->map->count()
                ->toArray(),
            'average_accesses_per_user' => $activeAccess->count() > 0 ? 
                $activeAccess->sum('access_count') / $activeAccess->count() : 0,
            'most_active_users' => $activeAccess->sortByDesc('access_count')
                ->take(5)
                ->map(function ($access) {
                    return [
                        'user' => $access->user->username,
                        'accesses' => $access->access_count,
                        'permission_level' => $access->getPermissionLevelDisplayName(),
                    ];
                })
                ->values(),
        ];
    }

    /**
     * Validate permission level
     */
    public static function isValidPermissionLevel(string $permissionLevel): bool
    {
        $validLevels = ['view', 'comment', 'download', 'upload', 'edit', 'admin'];
        return in_array($permissionLevel, $validLevels);
    }

    /**
     * Get permission hierarchy level
     */
    public static function getPermissionHierarchyLevel(string $permissionLevel): int
    {
        return match($permissionLevel) {
            'view' => 1,
            'comment' => 2,
            'download' => 3,
            'upload' => 4,
            'edit' => 5,
            'admin' => 6,
            default => 0
        };
    }

    /**
     * Check if user has sufficient permission
     */
    public static function hasSufficientPermission(
        DomicileSharedAccess $access,
        string $requiredPermission
    ): bool {
        $userLevel = static::getPermissionHierarchyLevel($access->permission_level);
        $requiredLevel = static::getPermissionHierarchyLevel($requiredPermission);
        
        return $userLevel >= $requiredLevel;
    }
}
