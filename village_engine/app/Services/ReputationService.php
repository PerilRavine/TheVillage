<?php

namespace App\Services;

use App\Models\User;
use App\Models\Village;
use App\Models\ReputationTransaction;
use App\Models\ContextualReputation;
use App\Models\Vouch;
use App\Models\UserVillage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReputationService
{
    // Configuration constants
    const DECAY_RATE = 0.02; // 2% per day
    const INERTIA_COEFFICIENT = 1.0; // Base inertia
    const VOUCH_BONUS_RATE = 0.20; // 20% bonus for successful vouches
    const MAXIMUM_STAKE_PERCENTAGE = 0.50; // Maximum 50% of available integrity
    const MINIMUM_STAKE_AMOUNT = 1.0; // Minimum stake amount
    const VOUCH_EXPIRY_DAYS = 30; // Vouch expiry period

    /**
     * Calculate user's integrity score with time-based erosion
     */
    public function calculateIntegrityScore(User $user): float
    {
        $baseIntegrity = $user->base_integrity;
        $lastActivity = $user->last_activity_at;
        
        if (!$lastActivity) {
            return $baseIntegrity;
        }

        $daysSinceActivity = $lastActivity->diffInDays(now());
        $inertiaCoefficient = $this->calculateInertiaCoefficient($user);
        
        // Apply exponential decay with inertia
        $decayFactor = exp(-self::DECAY_RATE * $daysSinceActivity / $inertiaCoefficient);
        
        return $baseIntegrity * $decayFactor;
    }

    /**
     * Calculate contextual reputation for a user in a specific context
     */
    public function calculateContextualReputation(
        User $user, 
        string $contextType, 
        ?int $contextId = null
    ): float {
        $contextualReputation = $user->getContextualReputation($contextType, $contextId);
        
        if (!$contextualReputation) {
            return 0.0;
        }

        // Calculate weighted components
        $interactionScore = $this->calculateInteractionScore($contextualReputation);
        $vouchWeight = $this->calculateVouchWeight($contextualReputation);
        $penaltyWeight = $this->calculatePenaltyWeight($contextualReputation);
        $timeWeight = $this->calculateTimeWeight($contextualReputation);

        // Apply weights: 40% interactions, 30% vouches, 20% penalties, 10% time
        $reputationScore = (
            $interactionScore * 0.40 +
            $vouchWeight * 0.30 +
            $penaltyWeight * 0.20 +
            $timeWeight * 0.10
        );

        return max(0.0, min(100.0, $reputationScore));
    }

    /**
     * Create a vouch from voucher to vouchee
     */
    public function createVouch(
        User $voucher,
        User $vouchee,
        float $stakeAmount,
        string $contextType = ContextualReputation::CONTEXT_VILLAGE,
        ?int $contextId = null,
        ?array $conditions = null
    ): Vouch {
        // Validate stake amount
        if (!$this->canUserStake($voucher, $stakeAmount)) {
            throw new \InvalidArgumentException('Invalid stake amount');
        }

        // Check for existing active vouch
        $existingVouch = Vouch::where('voucher_id', $voucher->id)
            ->where('vouchee_id', $vouchee->id)
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->pending()
            ->first();

        if ($existingVouch) {
            throw new \InvalidArgumentException('Active vouch already exists');
        }

        return DB::transaction(function () use ($voucher, $vouchee, $stakeAmount, $contextType, $contextId, $conditions) {
            // Lock stake amount
            $voucher->lockIntegrity($stakeAmount);

            // Create vouch record
            $vouch = Vouch::create([
                'voucher_id' => $voucher->id,
                'vouchee_id' => $vouchee->id,
                'context_type' => $contextType,
                'context_id' => $contextId,
                'stake_amount' => $stakeAmount,
                'bonus_amount' => $stakeAmount * self::VOUCH_BONUS_RATE,
                'forfeit_amount' => $stakeAmount,
                'status' => Vouch::STATUS_PENDING,
                'conditions' => $conditions,
                'target_role' => Vouch::TARGET_DENIZEN,
                'locked_at' => now(),
                'expires_at' => now()->addDays(self::VOUCH_EXPIRY_DAYS),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Record stake lock transaction
            ReputationTransaction::create([
                'user_id' => $voucher->id,
                'transaction_type' => ReputationTransaction::TYPE_STAKE_LOCK,
                'context_type' => $contextType,
                'context_id' => $contextId,
                'amount' => -$stakeAmount,
                'balance_before' => $voucher->getAvailableIntegrity(),
                'balance_after' => $voucher->getAvailableIntegrity() - $stakeAmount,
                'related_user_id' => $vouchee->id,
                'vouch_id' => $vouch->id,
                'reason' => 'Integrity staked for vouch',
                'metadata' => [
                    'stake_amount' => $stakeAmount,
                    'vouchee_id' => $vouchee->id,
                    'context_type' => $contextType,
                    'context_id' => $contextId,
                ],
            ]);

            return $vouch;
        });
    }

    /**
     * Process expired and completed vouches
     */
    public function processVouches(): void
    {
        // Process expired vouches
        $expiredVouches = Vouch::expired()->get();
        
        foreach ($expiredVouches as $vouch) {
            $vouch->markAsExpired();
        }

        // Process vouches where target was achieved
        $pendingVouches = Vouch::pending()->get();
        
        foreach ($pendingVouches as $vouch) {
            if ($vouch->checkTargetAchievement()) {
                $vouch->markAsSuccessful();
            }
        }
    }

    /**
     * Apply time-based erosion to all users
     */
    public function applyTimeErosion(): void
    {
        $users = User::where('last_activity_at', '<', now()->subDays(1))->get();
        
        foreach ($users as $user) {
            $currentScore = $this->calculateIntegrityScore($user);
            $newScore = $currentScore * (1 - self::DECAY_RATE);
            $difference = $newScore - $currentScore;
            
            if (abs($difference) > 0.01) { // Only apply if change is significant
                ReputationTransaction::create([
                    'user_id' => $user->id,
                    'transaction_type' => ReputationTransaction::TYPE_TIME_EROSION,
                    'context_type' => ContextualReputation::CONTEXT_GLOBAL,
                    'amount' => $difference,
                    'balance_before' => $user->base_integrity,
                    'balance_after' => $user->base_integrity + $difference,
                    'reason' => 'Time-based integrity erosion',
                    'metadata' => [
                        'decay_rate' => self::DECAY_RATE,
                        'days_since_activity' => $user->last_activity_at->diffInDays(now()),
                        'inertia_coefficient' => $this->calculateInertiaCoefficient($user),
                    ],
                ]);

                $user->update(['base_integrity' => $newScore]);
            }
        }
    }

    /**
     * Grant identity verification bonus
     */
    public function grantIdentityVerificationBonus(User $user): void
    {
        $bonusAmount = 10.0; // 10 points for identity verification
        
        ReputationTransaction::create([
            'user_id' => $user->id,
            'transaction_type' => ReputationTransaction::TYPE_VERIFICATION_BONUS,
            'context_type' => ContextualReputation::CONTEXT_GLOBAL,
            'amount' => $bonusAmount,
            'balance_before' => $user->getTotalIntegrity(),
            'balance_after' => $user->getTotalIntegrity() + $bonusAmount,
            'reason' => 'Identity verification bonus',
            'metadata' => [
                'verification_method' => $user->verification_method,
                'bonus_amount' => $bonusAmount,
            ],
        ]);

        $user->grantIntegrity($bonusAmount);
        $user->update([
            'identity_verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Record a breach of trust
     */
    public function recordBreach(
        User $user,
        User $victim,
        string $contextType,
        ?int $contextId = null,
        string $reason = null
    ): void {
        $penaltyAmount = 10.0; // Base penalty for breach
        
        ReputationTransaction::create([
            'user_id' => $user->id,
            'transaction_type' => ReputationTransaction::TYPE_BREACH_COMMITTED,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'amount' => -$penaltyAmount,
            'balance_before' => $user->getTotalIntegrity(),
            'balance_after' => $user->getTotalIntegrity() - $penaltyAmount,
            'related_user_id' => $victim->id,
            'reason' => $reason ?? 'Breach of trust',
            'metadata' => [
                'penalty_amount' => $penaltyAmount,
                'victim_id' => $victim->id,
                'context_type' => $contextType,
                'context_id' => $contextId,
            ],
        ]);

        $user->deductIntegrity($penaltyAmount);

        // Update contextual reputation
        $contextualReputation = $user->getContextualReputation($contextType, $contextId);
        if ($contextualReputation) {
            $contextualReputation->recordBreach();
        }
    }

    /**
     * Record a betrayal (serious breach)
     */
    public function recordBetrayal(
        User $user,
        User $victim,
        string $contextType,
        ?int $contextId = null,
        string $reason = null
    ): void {
        $penaltyAmount = 25.0; // Higher penalty for betrayal
        
        ReputationTransaction::create([
            'user_id' => $user->id,
            'transaction_type' => ReputationTransaction::TYPE_BETRAYAL_COMMITTED,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'amount' => -$penaltyAmount,
            'balance_before' => $user->getTotalIntegrity(),
            'balance_after' => $user->getTotalIntegrity() - $penaltyAmount,
            'related_user_id' => $victim->id,
            'reason' => $reason ?? 'Betrayal of trust',
            'metadata' => [
                'penalty_amount' => $penaltyAmount,
                'victim_id' => $victim->id,
                'context_type' => $contextType,
                'context_id' => $contextId,
            ],
        ]);

        $user->deductIntegrity($penaltyAmount);

        // Update contextual reputation
        $contextualReputation = $user->getContextualReputation($contextType, $contextId);
        if ($contextualReputation) {
            $contextualReputation->recordBetrayal();
        }
    }

    /**
     * Process reconciliation (forgiveness)
     */
    public function processReconciliation(
        User $user,
        User $forgivenBy,
        string $contextType,
        ?int $contextId = null,
        float $forgivenessAmount = null
    ): void {
        $reconciliationAmount = $forgivenessAmount ?? 5.0; // Default reconciliation amount
        
        ReputationTransaction::create([
            'user_id' => $user->id,
            'transaction_type' => ReputationTransaction::TYPE_RECONCILIATION,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'amount' => $reconciliationAmount,
            'balance_before' => $user->getTotalIntegrity(),
            'balance_after' => $user->getTotalIntegrity() + $reconciliationAmount,
            'related_user_id' => $forgivenBy->id,
            'reason' => 'Reconciliation and forgiveness',
            'metadata' => [
                'reconciliation_amount' => $reconciliationAmount,
                'forgiven_by_id' => $forgivenBy->id,
                'context_type' => $contextType,
                'context_id' => $contextId,
            ],
        ]);

        $user->grantIntegrity($reconciliationAmount);

        // Update contextual reputation
        $contextualReputation = $user->getContextualReputation($contextType, $contextId);
        if ($contextualReputation) {
            $contextualReputation->recordReconciliation();
        }
    }

    /**
     * Calculate inertia coefficient based on user activity patterns
     */
    private function calculateInertiaCoefficient(User $user): float
    {
        if (!$user->last_activity_at) {
            return self::INERTIA_COEFFICIENT;
        }

        $daysSinceActivity = $user->last_activity_at->diffInDays(now());
        
        // Higher inertia for users with consistent activity
        if ($daysSinceActivity <= 7) {
            return self::INERTIA_COEFFICIENT * 2.0; // High inertia
        } elseif ($daysSinceActivity <= 30) {
            return self::INERTIA_COEFFICIENT * 1.5; // Medium inertia
        } else {
            return self::INERTIA_COEFFICIENT; // Base inertia
        }
    }

    /**
     * Check if user can stake the specified amount
     */
    private function canUserStake(User $user, float $amount): bool
    {
        return $user->canStakeIntegrity($amount) &&
               $amount >= self::MINIMUM_STAKE_AMOUNT &&
               $amount <= $user->getAvailableIntegrity() * self::MAXIMUM_STAKE_PERCENTAGE;
    }

    /**
     * Calculate interaction score for contextual reputation
     */
    private function calculateInteractionScore(ContextualReputation $contextualReputation): float
    {
        // Base score from interactions
        $baseScore = min($contextualReputation->interactions_count * 0.5, 40.0);
        
        // Bonus for consistent activity
        $activityBonus = min($contextualReputation->days_active_in_context * 0.1, 10.0);
        
        return $baseScore + $activityBonus;
    }

    /**
     * Calculate vouch weight for contextual reputation
     */
    private function calculateVouchWeight(ContextualReputation $contextualReputation): float
    {
        return min($contextualReputation->vouches_received_count * 2.0, 30.0);
    }

    /**
     * Calculate penalty weight for contextual reputation
     */
    private function calculatePenaltyWeight(ContextualReputation $contextualReputation): float
    {
        $breachPenalty = $contextualReputation->breaches_count * 2.0;
        $betrayalPenalty = $contextualReputation->betrayals_count * 5.0;
        $reconciliationBonus = $contextualReputation->reconciliations_received_count * 1.0;
        
        return max(-20.0, -($breachPenalty + $betrayalPenalty - $reconciliationBonus));
    }

    /**
     * Calculate time weight for contextual reputation
     */
    private function calculateTimeWeight(ContextualReputation $contextualReputation): float
    {
        return min($contextualReputation->days_active_in_context * 0.05, 10.0);
    }

    /**
     * Get user's reputation summary across all contexts
     */
    public function getReputationSummary(User $user): array
    {
        return [
            'global_integrity' => $this->calculateIntegrityScore($user),
            'available_integrity' => $user->getAvailableIntegrity(),
            'locked_integrity' => $user->getLockedIntegrity(),
            'base_role' => $user->role,
            'contextual_reputations' => $user->contextualReputations->map(function ($context) {
                return [
                    'context_type' => $context->context_type,
                    'context_id' => $context->context_id,
                    'reputation_score' => $this->calculateContextualReputation(
                        $user, 
                        $context->context_type, 
                        $context->context_id
                    ),
                    'role_in_context' => $context->role_in_context,
                    'trust_level' => $context->trust_level,
                    'days_active' => $context->days_active_in_context,
                ];
            })->toArray(),
            'active_vouches' => $user->vouchesGiven()->pending()->count(),
            'completed_vouches' => $user->vouchesGiven()->resolved()->count(),
            'staked_integrity' => $user->vouchesGiven()->pending()->sum('stake_amount'),
        ];
    }

    /**
     * Get village reputation statistics
     */
    public function getVillageReputationStats(Village $village): array
    {
        $members = $village->users()->active()->get();
        
        return [
            'total_members' => $members->count(),
            'average_reputation' => $members->avg(function ($user) {
                return $this->calculateContextualReputation(
                    $user, 
                    ContextualReputation::CONTEXT_VILLAGE, 
                    $village->id
                );
            }),
            'denizen_count' => $members->where('pivot.role', UserVillage::ROLE_DENIZEN)->count(),
            'steward_count' => $members->where('pivot.role', UserVillage::ROLE_STEWARD)->count(),
            'elder_count' => $members->where('pivot.role', UserVillage::ROLE_ELDER)->count(),
            'total_vouches' => Vouch::where('context_type', ContextualReputation::CONTEXT_VILLAGE)
                ->where('context_id', $village->id)
                ->count(),
            'successful_vouches' => Vouch::where('context_type', ContextualReputation::CONTEXT_VILLAGE)
                ->where('context_id', $village->id)
                ->where('status', Vouch::STATUS_SUCCESSFUL)
                ->count(),
            'failed_vouches' => Vouch::where('context_type', ContextualReputation::CONTEXT_VILLAGE)
                ->where('context_id', $village->id)
                ->where('status', Vouch::STATUS_FAILED)
                ->count(),
        ];
    }
}
