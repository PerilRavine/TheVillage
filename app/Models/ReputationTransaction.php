<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReputationTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_type',
        'context_type',
        'context_id',
        'amount',
        'balance_before',
        'balance_after',
        'related_user_id',
        'village_id',
        'vouch_id',
        'reason',
        'metadata',
        'notes',
        'status',
        'processed_at',
        'processed_by',
        'ip_address',
        'user_agent',
        'signature_proof',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'balance_before' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'metadata' => 'array',
        'signature_proof' => 'array',
        'processed_at' => 'datetime',
    ];

    // Transaction types
    const TYPE_INTEGRITY_CHANGE = 'integrity_change';
    const TYPE_CONTEXTUAL_REPUTATION = 'contextual_reputation';
    const TYPE_STAKE_LOCK = 'stake_lock';
    const TYPE_STAKE_RELEASE = 'stake_release';
    const TYPE_STAKE_FORFEIT = 'stake_forfeit';
    const TYPE_VOUCH_CREATED = 'vouch_created';
    const TYPE_VOUCH_RESOLVED = 'vouch_resolved';
    const TYPE_BREACH_COMMITTED = 'breach_committed';
    const TYPE_BETRAYAL_COMMITTED = 'betrayal_committed';
    const TYPE_RECONCILIATION = 'reconciliation';
    const TYPE_ROLE_CHANGE = 'role_change';
    const TYPE_TIME_EROSION = 'time_erosion';
    const TYPE_ACTIVITY_BONUS = 'activity_bonus';
    const TYPE_VERIFICATION_BONUS = 'verification_bonus';

    // Context types
    const CONTEXT_VILLAGE = 'village';
    const CONTEXT_MAP = 'map';
    const CONTEXT_GLOBAL = 'global';

    // Status types
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSED = 'processed';
    const STATUS_FAILED = 'failed';
    const STATUS_REVERSED = 'reversed';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function vouch(): BelongsTo
    {
        return $this->belongsTo(Vouch::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Helper methods
    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isIntegrityTransaction(): bool
    {
        return in_array($this->transaction_type, [
            self::TYPE_INTEGRITY_CHANGE,
            self::TYPE_STAKE_LOCK,
            self::TYPE_STAKE_RELEASE,
            self::TYPE_STAKE_FORFEIT,
            self::TYPE_TIME_EROSION,
            self::TYPE_ACTIVITY_BONUS,
            self::TYPE_VERIFICATION_BONUS,
        ]);
    }

    public function isContextualReputationTransaction(): bool
    {
        return $this->transaction_type === self::TYPE_CONTEXTUAL_REPUTATION;
    }

    public function isVouchTransaction(): bool
    {
        return in_array($this->transaction_type, [
            self::TYPE_VOUCH_CREATED,
            self::TYPE_VOUCH_RESOLVED,
        ]);
    }

    public function isPenaltyTransaction(): bool
    {
        return in_array($this->transaction_type, [
            self::TYPE_BREACH_COMMITTED,
            self::TYPE_BETRAYAL_COMMITTED,
        ]);
    }

    public function getContextDescription(): string
    {
        switch ($this->context_type) {
            case self::CONTEXT_VILLAGE:
                $village = $this->village;
                return $village ? "Village: {$village->name}" : 'Unknown Village';
            case self::CONTEXT_MAP:
                return 'Global Map';
            case self::CONTEXT_GLOBAL:
                return 'Global Context';
            default:
                return 'Unknown Context';
        }
    }

    public function getTransactionDescription(): string
    {
        $descriptions = [
            self::TYPE_INTEGRITY_CHANGE => 'Integrity Score Change',
            self::TYPE_CONTEXTUAL_REPUTATION => 'Contextual Reputation Change',
            self::TYPE_STAKE_LOCK => 'Integrity Staked',
            self::TYPE_STAKE_RELEASE => 'Stake Released',
            self::TYPE_STAKE_FORFEIT => 'Stake Forfeited',
            self::TYPE_VOUCH_CREATED => 'Vouch Created',
            self::TYPE_VOUCH_RESOLVED => 'Vouch Resolved',
            self::TYPE_BREACH_COMMITTED => 'Breach Committed',
            self::TYPE_BETRAYAL_COMMITTED => 'Betrayal Committed',
            self::TYPE_RECONCILIATION => 'Reconciliation',
            self::TYPE_ROLE_CHANGE => 'Role Change',
            self::TYPE_TIME_EROSION => 'Time-Based Erosion',
            self::TYPE_ACTIVITY_BONUS => 'Activity Bonus',
            self::TYPE_VERIFICATION_BONUS => 'Identity Verification Bonus',
        ];

        return $descriptions[$this->transaction_type] ?? 'Unknown Transaction';
    }

    public function getFormattedAmount(): string
    {
        $prefix = $this->isPositive() ? '+' : '';
        return $prefix . number_format($this->amount, 4);
    }

    public function getFormattedBalanceBefore(): string
    {
        return number_format($this->balance_before, 4);
    }

    public function getFormattedBalanceAfter(): string
    {
        return number_format($this->balance_after, 4);
    }

    // Scopes
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeInContext($query, string $contextType, ?int $contextId = null)
    {
        return $query->where('context_type', $contextType)
                    ->when($contextId, function ($query) use ($contextId) {
                        return $query->where('context_id', $contextId);
                    });
    }

    public function scopePositive($query)
    {
        return $query->where('amount', '>', 0);
    }

    public function scopeNegative($query)
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
