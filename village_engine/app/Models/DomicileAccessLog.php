<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * DomicileAccessLog - Log all access to personal spaces
 * 
 * Tracks all access attempts and actions within user domiciles
 * for security auditing and analytics purposes
 */
class DomicileAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'domicile_id',
        'user_id',
        'action',
        'target_type',
        'target_id',
        'ip_address',
        'user_agent',
        'details',
        'success',
        'failure_reason',
    ];

    protected $casts = [
        'success' => 'boolean',
        'details' => 'array',
    ];

    /**
     * Get domicile that was accessed
     */
    public function domicile(): BelongsTo
    {
        return $this->belongsTo(Domicile::class, 'domicile_id');
    }

    /**
     * Get user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the target model (polymorphic)
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get action display name
     */
    public function getActionDisplayName(): string
    {
        return match($this->action) {
            'view' => 'Viewed',
            'download' => 'Downloaded',
            'upload' => 'Uploaded',
            'delete' => 'Deleted',
            'share' => 'Shared',
            'access_granted' => 'Access Granted',
            'access_denied' => 'Access Denied',
            'login' => 'Logged In',
            'logout' => 'Logged Out',
            default => 'Unknown Action'
        };
    }

    /**
     * Get status display
     */
    public function getStatusDisplay(): string
    {
        return $this->success ? 'Success' : 'Failed';
    }

    /**
     * Get formatted timestamp
     */
    public function getFormattedTimestamp(): string
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }

    /**
     * Get user agent parsed
     */
    public function getParsedUserAgent(): array
    {
        $userAgent = $this->user_agent;
        
        return [
            'browser' => $this->extractBrowser($userAgent),
            'os' => $this->extractOS($userAgent),
            'device' => $this->extractDevice($userAgent),
            'is_mobile' => $this->isMobile($userAgent),
        ];
    }

    /**
     * Extract browser from user agent
     */
    private function extractBrowser(string $userAgent): string
    {
        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            return 'Firefox';
        } elseif (str_contains($userAgent, 'Safari')) {
            return 'Safari';
        } elseif (str_contains($userAgent, 'Edge')) {
            return 'Edge';
        } elseif (str_contains($userAgent, 'Opera')) {
            return 'Opera';
        }
        
        return 'Unknown';
    }

    /**
     * Extract OS from user agent
     */
    private function extractOS(string $userAgent): string
    {
        if (str_contains($userAgent, 'Windows')) {
            return 'Windows';
        } elseif (str_contains($userAgent, 'Mac')) {
            return 'macOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            return 'Linux';
        } elseif (str_contains($userAgent, 'Android')) {
            return 'Android';
        } elseif (str_contains($userAgent, 'iOS')) {
            return 'iOS';
        }
        
        return 'Unknown';
    }

    /**
     * Extract device type from user agent
     */
    private function extractDevice(string $userAgent): string
    {
        if (str_contains($userAgent, 'Mobile')) {
            return 'Mobile';
        } elseif (str_contains($userAgent, 'Tablet')) {
            return 'Tablet';
        }
        
        return 'Desktop';
    }

    /**
     * Check if user agent is mobile
     */
    private function isMobile(string $userAgent): bool
    {
        return str_contains($userAgent, 'Mobile') || 
               str_contains($userAgent, 'Android') || 
               str_contains($userAgent, 'iPhone') || 
               str_contains($userAgent, 'iPad');
    }

    /**
     * Get security risk level
     */
    public function getSecurityRiskLevel(): string
    {
        $risk = 0;
        
        // Check for suspicious IP patterns
        if ($this->isSuspiciousIP()) {
            $risk += 3;
        }
        
        // Check for suspicious user agent
        if ($this->isSuspiciousUserAgent()) {
            $risk += 2;
        }
        
        // Check for failed access attempts
        if (!$this->success) {
            $risk += 1;
        }
        
        return match(true) {
            $risk >= 5 => 'High',
            $risk >= 3 => 'Medium',
            $risk >= 1 => 'Low',
            default => 'Minimal'
        };
    }

    /**
     * Check if IP is suspicious
     */
    private function isSuspiciousIP(): bool
    {
        $ip = $this->ip_address;
        
        // Check for known malicious IP ranges
        $suspiciousRanges = [
            '10.0.0.0/8',     // Private network (potential VPN)
            '172.16.0.0/12',   // Private network (potential VPN)
            '192.168.0.0/16',  // Private network (potential VPN)
        ];
        
        foreach ($suspiciousRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user agent is suspicious
     */
    private function isSuspiciousUserAgent(): bool
    {
        $userAgent = strtolower($this->user_agent);
        
        $suspiciousPatterns = [
            'bot',
            'crawler',
            'spider',
            'scraper',
            'automated',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if IP is in range
     */
    private function ipInRange(string $ip, string $range): bool
    {
        // Simplified IP range checking
        [$rangeIP, $mask] = explode('/', $range);
        $rangeLong = ip2long($rangeIP);
        $ipLong = ip2long($ip);
        $maskLong = -1 << (32 - (int)$mask);
        
        return ($ipLong & $maskLong) === ($rangeLong & $maskLong);
    }

    /**
     * Get access statistics for domicile
     */
    public static function getAccessStatistics(Domicile $domicile, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        return static::where('domicile_id', $domicile->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_accesses,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_accesses,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_accesses,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT ip_address) as unique_ips
            ')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get recent access logs for user
     */
    public static function getRecentAccessForUser(User $user, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['domicile'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get failed access attempts
     */
    public static function getFailedAccessAttempts(Domicile $domicile, int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('domicile_id', $domicile->id)
            ->where('success', false)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create access log entry
     */
    public static function logAccess(
        Domicile $domicile,
        User $user,
        string $action,
        ?Model $target = null,
        bool $success = true,
        ?string $failureReason = null
    ): self {
        return static::create([
            'domicile_id' => $domicile->id,
            'user_id' => $user->id,
            'action' => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target?->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'success' => $success,
            'failure_reason' => $failureReason,
            'details' => [
                'timestamp' => now()->toISOString(),
                'session_id' => session()->getId(),
            ],
        ]);
    }
}
