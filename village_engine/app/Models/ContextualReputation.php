<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContextualReputation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'context_type',
        'context_id',
        'reputation_score',
        'interaction_score',
        'vouch_weight',
        'penalty_weight',
        'time_weight',
        'role_in_context',
        'joined_context_at',
        'last_activity_in_context',
        'days_active_in_context',
        'interactions_count',
        'vouches_received_count',
        'vouches_given_count',
        'breaches_count',
        'betrayals_count',
        'reconciliations_received_count',
        'trust_level',
        'reliability_score',
        'integrity_score',
    ];

    protected $casts = [
        'reputation_score' => 'decimal:4',
        'interaction_score' => 'decimal:4',
        'vouch_weight' => 'decimal:4',
        'penalty_weight' => 'decimal:4',
        'time_weight' => 'decimal:4',
        'joined_context_at' => 'datetime',
        'last_activity_in_context' => 'datetime',
        'trust_level' => 'decimal:4',
        'reliability_score' => 'decimal:4',
        'integrity_score' => 'decimal:4',
    ];

    // Context types
    const CONTEXT_VILLAGE = 'village';
    const CONTEXT_MAP = 'map';
    const CONTEXT_GLOBAL = 'global';

    // Context roles
    const ROLE_STRANGER = 'stranger';
    const ROLE_SOJOURNER = 'sojourner';
    const ROLE_DENIZEN = 'denizen';
    const ROLE_STEWARD = 'steward';
    const ROLE_ELDER = 'elder';
    const ROLE_NON_MEMBER = 'non_member';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'context_id');
    }

    // Helper methods
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

    public function isMember(): bool
    {
        return in_array($this->role_in_context, [
            self::ROLE_SOJOURNER,
            self::ROLE_DENIZEN,
            self::ROLE_STEWARD,
            self::ROLE_ELDER,
        ]);
    }

    public function isDenizenOrHigher(): bool
    {
        return in_array($this->role_in_context, [
            self::ROLE_DENIZEN,
            self::ROLE_STEWARD,
            self::ROLE_ELDER,
        ]);
    }

    public function isStewardOrHigher(): bool
    {
        return in_array($this->role_in_context, [
            self::ROLE_STEWARD,
            self::ROLE_ELDER,
        ]);
    }

    public function isElder(): bool
    {
        return $this->role_in_context === self::ROLE_ELDER;
    }

    public function updateReputationScore(): void
    {
        // Calculate weighted reputation score
        $this->reputation_score = (
            $this->interaction_score * 0.40 +  // 40% weight
            $this->vouch_weight * 0.30 +       // 30% weight
            $this->penalty_weight * 0.20 +      // 20% weight (negative)
            $this->time_weight * 0.10           // 10% weight
        );

        // Ensure score stays within bounds
        $this->reputation_score = max(0.0, min(100.0, $this->reputation_score));
        
        $this->save();
    }

    public function updateTrustLevel(): void
    {
        // Calculate trust level based on multiple factors
        $baseTrust = $this->reputation_score / 100.0;
        $reliabilityBonus = $this->reliability_score * 0.3;
        $integrityBonus = $this->integrity_score * 0.3;
        $activityBonus = min($this->days_active_in_context / 365.0, 1.0) * 0.1;
        $vouchBonus = min($this->vouches_received_count / 10.0, 1.0) * 0.1;
        $breachPenalty = min($this->breaches_count * 0.1, 0.5);

        $this->trust_level = max(0.0, min(1.0, 
            $baseTrust + $reliabilityBonus + $integrityBonus + $activityBonus + $vouchBonus - $breachPenalty
        ));

        $this->save();
    }

    public function recordInteraction(): void
    {
        $this->increment('interactions_count');
        $this->updateLastActivity();
    }

    public function updateLastActivity(): void
    {
        $this->update(['last_activity_in_context' => now()]);
    }

    public function incrementDaysActive(): void
    {
        $this->increment('days_active_in_context');
    }

    public function recordVouchReceived(): void
    {
        $this->increment('vouches_received_count');
        $this->increment('vouch_weight', 5.0); // Each vouch adds 5 points
        $this->updateReputationScore();
    }

    public function recordVouchGiven(): void
    {
        $this->increment('vouches_given_count');
    }

    public function recordBreach(): void
    {
        $this->increment('breaches_count');
        $this->decrement('penalty_weight', 10.0); // Each breach subtracts 10 points
        $this->updateReputationScore();
        $this->updateTrustLevel();
    }

    public function recordBetrayal(): void
    {
        $this->increment('betrayals_count');
        $this->decrement('penalty_weight', 25.0); // Each betrayal subtracts 25 points
        $this->updateReputationScore();
        $this->updateTrustLevel();
    }

    public function recordReconciliation(): void
    {
        $this->increment('reconciliations_received_count');
        $this->increment('penalty_weight', 2.0); // Reconciliation adds back 2 points
        $this->updateReputationScore();
        $this->updateTrustLevel();
    }

    // Scopes
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeInContext($query, string $contextType, ?int $contextId = null)
    {
        return $query->where('context_type', $contextType)
                    ->when($contextId, function ($query) use ($contextId) {
                        return $query->where('context_id', $contextId);
                    });
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where('role_in_context', $role);
    }

    public function scopeActive($query)
    {
        return $query->where('last_activity_in_context', '>=', now()->subDays(30));
    }

    public function scopeHighTrust($query)
    {
        return $query->where('trust_level', '>=', 0.7);
    }

    public function scopeSortedByReputation($query)
    {
        return $query->orderBy('reputation_score', 'desc');
    }

    public function scopeSortedByTrust($query)
    {
        return $query->orderBy('trust_level', 'desc');
    }
}
