<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vouch extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'vouchee_id',
        'context_type',
        'context_id',
        'stake_amount',
        'bonus_amount',
        'forfeit_amount',
        'status',
        'conditions',
        'target_role',
        'target_reputation',
        'locked_at',
        'expires_at',
        'resolved_at',
        'target_achieved_at',
        'resolution_reason',
        'resolution_metadata',
        'ip_address',
        'user_agent',
        'signature_proof',
    ];

    protected $casts = [
        'stake_amount' => 'decimal:4',
        'bonus_amount' => 'decimal:4',
        'forfeit_amount' => 'decimal:4',
        'conditions' => 'array',
        'target_reputation' => 'decimal:4',
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
        'resolved_at' => 'datetime',
        'target_achieved_at' => 'datetime',
        'resolution_metadata' => 'array',
        'signature_proof' => 'array',
    ];

    // Vouch statuses
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESSFUL = 'successful';
    const STATUS_FAILED = 'failed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    // Context types
    const CONTEXT_VILLAGE = 'village';
    const CONTEXT_MAP = 'map';
    const CONTEXT_GLOBAL = 'global';

    // Target roles
    const TARGET_SOJOURNER = 'sojourner';
    const TARGET_DENIZEN = 'denizen';

    // Relationships
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voucher_id');
    }

    public function vouchee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vouchee_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'context_id');
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESSFUL;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isVillageContext(): bool
    {
        return $this->context_type === self::CONTEXT_VILLAGE;
    }

    public function isMapContext(): bool
    {
        return $this->context_type === self::CONTEXT_MAP;
    }

    public function isGlobalContext(): bool
    {
        return $this->context_type === self::CONTEXT_GLOBAL;
    }

    public function isExpiredDate(): bool
    {
        return $this->expires_at->isPast();
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending() && !$this->isExpiredDate();
    }

    public function getTotalReturn(): float
    {
        return (float) $this->stake_amount + (float) $this->bonus_amount;
    }

    public function getForfeitAmount(): float
    {
        return (float) $this->forfeit_amount;
    }

    public function markAsSuccessful(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_SUCCESSFUL,
            'resolved_at' => now(),
            'target_achieved_at' => now(),
            'resolution_reason' => $reason ?? 'Vouchee achieved target role',
        ]);

        // Return stake to voucher with bonus
        $this->voucher->unlockIntegrity($this->getTotalReturn());
        $this->voucher->grantIntegrity($this->bonus_amount);

        // Record successful vouch transaction
        ReputationTransaction::create([
            'user_id' => $this->voucher_id,
            'transaction_type' => ReputationTransaction::TYPE_VOUCH_RESOLVED,
            'context_type' => $this->context_type,
            'context_id' => $this->context_id,
            'amount' => $this->bonus_amount,
            'balance_before' => $this->voucher->getTotalIntegrity(),
            'balance_after' => $this->voucher->getTotalIntegrity() + $this->bonus_amount,
            'related_user_id' => $this->vouchee_id,
            'vouch_id' => $this->id,
            'reason' => 'Successful vouch - bonus awarded',
            'metadata' => [
                'vouch_id' => $this->id,
                'stake_amount' => $this->stake_amount,
                'bonus_amount' => $this->bonus_amount,
                'target_role' => $this->target_role,
            ],
        ]);
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'resolved_at' => now(),
            'resolution_reason' => $reason ?? 'Vouch failed - conditions not met',
        ]);

        // Forfeit stake from voucher
        $this->voucher->deductIntegrity($this->getForfeitAmount());

        // Record failed vouch transaction
        ReputationTransaction::create([
            'user_id' => $this->voucher_id,
            'transaction_type' => ReputationTransaction::TYPE_STAKE_FORFEIT,
            'context_type' => $this->context_type,
            'context_id' => $this->context_id,
            'amount' => -$this->getForfeitAmount(),
            'balance_before' => $this->voucher->getTotalIntegrity(),
            'balance_after' => $this->voucher->getTotalIntegrity() - $this->getForfeitAmount(),
            'related_user_id' => $this->vouchee_id,
            'vouch_id' => $this->id,
            'reason' => 'Failed vouch - stake forfeited',
            'metadata' => [
                'vouch_id' => $this->id,
                'stake_amount' => $this->stake_amount,
                'forfeit_amount' => $this->getForfeitAmount(),
                'target_role' => $this->target_role,
            ],
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
            'resolved_at' => now(),
            'resolution_reason' => 'Vouch expired without resolution',
        ]);

        // Return stake to voucher (no bonus)
        $this->voucher->unlockIntegrity($this->stake_amount);

        // Record expired vouch transaction
        ReputationTransaction::create([
            'user_id' => $this->voucher_id,
            'transaction_type' => ReputationTransaction::TYPE_STAKE_RELEASE,
            'context_type' => $this->context_type,
            'context_id' => $this->context_id,
            'amount' => $this->stake_amount,
            'balance_before' => $this->voucher->getTotalIntegrity(),
            'balance_after' => $this->voucher->getTotalIntegrity() + $this->stake_amount,
            'related_user_id' => $this->vouchee_id,
            'vouch_id' => $this->id,
            'reason' => 'Expired vouch - stake returned',
            'metadata' => [
                'vouch_id' => $this->id,
                'stake_amount' => $this->stake_amount,
                'expired_at' => $this->expires_at,
            ],
        ]);
    }

    public function cancel(string $reason = null): void
    {
        if (!$this->canBeCancelled()) {
            throw new \Exception('Vouch cannot be cancelled');
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'resolved_at' => now(),
            'resolution_reason' => $reason ?? 'Vouch cancelled by voucher',
        ]);

        // Return stake to voucher
        $this->voucher->unlockIntegrity($this->stake_amount);

        // Record cancelled vouch transaction
        ReputationTransaction::create([
            'user_id' => $this->voucher_id,
            'transaction_type' => ReputationTransaction::TYPE_STAKE_RELEASE,
            'context_type' => $this->context_type,
            'context_id' => $this->context_id,
            'amount' => $this->stake_amount,
            'balance_before' => $this->voucher->getTotalIntegrity(),
            'balance_after' => $this->voucher->getTotalIntegrity() + $this->stake_amount,
            'related_user_id' => $this->vouchee_id,
            'vouch_id' => $this->id,
            'reason' => 'Cancelled vouch - stake returned',
            'metadata' => [
                'vouch_id' => $this->id,
                'stake_amount' => $this->stake_amount,
                'cancelled_at' => now(),
            ],
        ]);
    }

    public function checkTargetAchievement(): bool
    {
        if ($this->isResolved()) {
            return false;
        }

        $voucheeContext = $this->vouchee->getContextualReputation($this->context_type, $this->context_id);
        
        if (!$voucheeContext) {
            return false;
        }

        // Check if vouchee achieved target role
        if ($this->target_role === self::TARGET_DENIZEN) {
            return $voucheeContext->role_in_context === ContextualReputation::ROLE_DENIZEN;
        }

        if ($this->target_role === self::TARGET_SOJOURNER) {
            return $voucheeContext->role_in_context === ContextualReputation::ROLE_SOJOURNER;
        }

        // Check if vouchee achieved target reputation
        if ($this->target_reputation) {
            return $voucheeContext->reputation_score >= $this->target_reputation;
        }

        return false;
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESSFUL,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ]);
    }

    // Scopes
    public function scopeForVoucher($query, User $user)
    {
        return $query->where('voucher_id', $user->id);
    }

    public function scopeForVouchee($query, User $user)
    {
        return $query->where('vouchee_id', $user->id);
    }

    public function scopeInContext($query, string $contextType, ?int $contextId = null)
    {
        return $query->where('context_type', $contextType)
                    ->when($contextId, function ($query) use ($contextId) {
                        return $query->where('context_id', $contextId);
                    });
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeResolved($query)
    {
        return $query->whereIn('status', [
            self::STATUS_SUCCESSFUL,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ]);
    }

    public function scopeExpiring($query, int $days = 7)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
                    ->where('status', self::STATUS_PENDING);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
                    ->where('status', self::STATUS_PENDING);
    }
}
