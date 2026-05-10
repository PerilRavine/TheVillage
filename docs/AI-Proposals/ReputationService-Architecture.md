# Technical Implementation: ReputationService Architecture

## Service Interface Design

```php
<?php

namespace App\Services\Contracts;

use App\Models\User;
use App\Models\Village;
use App\ValueObjects\IntegrityScore;
use App\ValueObjects\Context;
use App\Models\Vouch;

interface ReputationServiceInterface
{
    public function calculateIntegrityScore(User $user, ?Context $context = null): IntegrityScore;
    public function getContextualReputation(User $user, Context $context): float;
    public function applyTimeErosion(User $user): IntegrityScore;
    public function vouch(User $voucher, User $vouchee, float $stakeAmount, Context $context): Vouch;
    public function resolveVouch(Vouch $vouch, bool $successful): void;
    public function getLockedStakes(User $user): Collection;
    public function canVouch(User $user, float $stakeAmount): bool;
}
```

## Core Implementation

```php
<?php

namespace App\Services;

use App\Services\Contracts\ReputationServiceInterface;
use App\Models\User;
use App\Models\Village;
use App\Models\Vouch;
use App\Repositories\ReputationRepository;
use App\Repositories\VouchRepository;
use App\ValueObjects\IntegrityScore;
use App\ValueObjects\Context;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class ReputationService implements ReputationServiceInterface
{
    private const INERTIA_DECAY_RATE = 0.02; // 2% daily decay rate
    private const VOUCH_BONUS_RATE = 0.20;   // 20% bonus for successful vouches
    private const MINIMUM_STAKE = 1.0;       // Minimum integrity stake
    private const MAXIMUM_STAKE_PERCENTAGE = 0.50; // Can stake max 50% of integrity
    
    public function __construct(
        private ReputationRepository $reputationRepository,
        private VouchRepository $vouchRepository
    ) {}

    public function calculateIntegrityScore(User $user, ?Context $context = null): IntegrityScore
    {
        // Get base integrity from user's actions and history
        $baseIntegrity = $this->reputationRepository->getBaseIntegrity($user);
        
        // Apply contextual adjustments if specified
        $contextualMultiplier = $context 
            ? $this->getContextualMultiplier($user, $context)
            : 1.0;
            
        // Apply time-based erosion
        $erodedIntegrity = $this->applyTimeErosion($user);
        
        // Calculate final integrity score
        $finalScore = $baseIntegrity * $contextualMultiplier * $erodedIntegrity->getScore();
        
        return new IntegrityScore(
            score: max(0, min(100, $finalScore)),
            context: $context,
            lastUpdated: now(),
            baseIntegrity: $baseIntegrity,
            contextualMultiplier: $contextualMultiplier,
            erosionApplied: $erodedIntegrity->getScore() < 1.0
        );
    }

    public function getContextualReputation(User $user, Context $context): float
    {
        $reputationData = $this->reputationRepository->getContextualReputation($user, $context);
        
        // Calculate contextual reputation based on:
        // 1. Direct interactions in context
        // 2. Vouches received in context
        // 3. Betrayals/breaches in context
        // 4. Time spent in context
        
        $directInteractions = $reputationData->getInteractionScore();
        $vouchWeight = $reputationData->getVouchWeight();
        $penaltyWeight = $reputationData->getPenaltyWeight();
        $timeWeight = $reputationData->getTimeWeight();
        
        // Weighted calculation
        $contextualScore = (
            $directInteractions * 0.4 +      // 40% weight on direct interactions
            $vouchWeight * 0.3 +            // 30% weight on vouches
            $penaltyWeight * 0.2 +           // 20% weight on penalties (negative)
            $timeWeight * 0.1                // 10% weight on time investment
        );
        
        return max(0, min(100, $contextualScore));
    }

    public function applyTimeErosion(User $user): IntegrityScore
    {
        $lastActivity = $this->reputationRepository->getLastActivityDate($user);
        $daysInactive = $lastActivity->diffInDays(now());
        
        if ($daysInactive <= 0) {
            return new IntegrityScore(score: 1.0); // No erosion for active users
        }
        
        // Calculate inertia coefficient based on user's historical activity patterns
        $inertiaCoefficient = $this->calculateInertiaCoefficient($user);
        
        // Apply exponential decay: score = e^(-decay_rate * days * inertia)
        $decayFactor = exp(-self::INERTIA_DECAY_RATE * $daysInactive * $inertiaCoefficient);
        
        return new IntegrityScore(
            score: $decayFactor,
            context: null,
            lastUpdated: now(),
            daysInactive: $daysInactive,
            inertiaCoefficient: $inertiaCoefficient
        );
    }

    public function vouch(User $voucher, User $vouchee, float $stakeAmount, Context $context): Vouch
    {
        // Validate vouching eligibility
        if (!$this->canVouch($voucher, $stakeAmount)) {
            throw new Exception('User cannot vouch with specified stake amount');
        }
        
        // Check if vouchee is already a denizen in this context
        if ($vouchee->getRoleInContext($context) >= Role::DENIZEN) {
            throw new Exception('Cannot vouch for user who is already a denizen');
        }
        
        return DB::transaction(function () use ($voucher, $vouchee, $stakeAmount, $context) {
            // Lock the stake amount from voucher's integrity
            $this->lockIntegrityStake($voucher, $stakeAmount);
            
            // Create the vouch record
            $vouch = Vouch::create([
                'voucher_id' => $voucher->id,
                'vouchee_id' => $vouchee->id,
                'stake_amount' => $stakeAmount,
                'context_type' => $context->getType(),
                'context_id' => $context->getId(),
                'status' => Vouch::STATUS_PENDING,
                'locked_at' => now(),
                'expires_at' => now()->addDays(30), // 30-day maturity period
            ]);
            
            // Apply immediate reputation impact to vouchee
            $this->applyVouchImpact($vouchee, $stakeAmount, $context);
            
            return $vouch;
        });
    }

    public function resolveVouch(Vouch $vouch, bool $successful): void
    {
        DB::transaction(function () use ($vouch, $successful) {
            $voucher = $vouch->voucher;
            $vouchee = $vouch->vouchee;
            
            if ($successful) {
                // Return stake with 20% bonus
                $returnedAmount = $vouch->stake_amount * (1 + self::VOUCH_BONUS_RATE);
                $this->unlockIntegrityStake($voucher, $vouch->stake_amount);
                $this->grantIntegrityBonus($voucher, $returnedAmount - $vouch->stake_amount);
                
                // Boost vouchee's reputation for achieving denizen status
                $this->grantDenizenBonus($vouchee, $vouch->context);
                
                $vouch->status = Vouch::STATUS_SUCCESSFUL;
            } else {
                // Forfeit the stake
                $this->forfeitIntegrityStake($voucher, $vouch->stake_amount);
                
                // Apply penalty to vouchee for not achieving denizen status
                $this->applyVouchFailurePenalty($vouchee, $vouch->context);
                
                $vouch->status = Vouch::STATUS_FAILED;
            }
            
            $vouch->resolved_at = now();
            $vouch->save();
        });
    }

    public function getLockedStakes(User $user): Collection
    {
        return $this->vouchRepository->getActiveVouchesByVoucher($user);
    }

    public function canVouch(User $user, float $stakeAmount): bool
    {
        // Check if user has sufficient integrity
        $currentIntegrity = $this->calculateIntegrityScore($user);
        $availableIntegrity = $currentIntegrity->getScore() - $this->getTotalLockedStakes($user);
        
        // Check minimum and maximum stake limits
        if ($stakeAmount < self::MINIMUM_STAKE) {
            return false;
        }
        
        if ($stakeAmount > $availableIntegrity * self::MAXIMUM_STAKE_PERCENTAGE) {
            return false;
        }
        
        // Check if user has required role to vouch
        return $user->role->index >= Role::DENIZEN->index;
    }

    // Private helper methods

    private function getContextualMultiplier(User $user, Context $context): float
    {
        $contextualReputation = $this->getContextualReputation($user, $context);
        $globalIntegrity = $this->calculateIntegrityScore($user)->getScore();
        
        // Contextual reputation can boost or diminish global integrity
        // Range: 0.8 to 1.2 based on contextual performance
        return 0.8 + ($contextualReputation / 100.0) * 0.4;
    }

    private function calculateInertiaCoefficient(User $user): float
    {
        // Inertia coefficient based on user's historical activity patterns
        // Active users get lower inertia (slower decay), inactive users get higher inertia
        $activityPattern = $this->reputationRepository->getActivityPattern($user);
        
        // Calculate average days between activities over the last 90 days
        $avgDaysBetweenActivities = $activityPattern->getAverageDaysBetweenActivities();
        
        // Inertia ranges from 0.5 (very active) to 2.0 (very inactive)
        return min(2.0, max(0.5, $avgDaysBetweenActivities / 7.0));
    }

    private function lockIntegrityStake(User $user, float $stakeAmount): void
    {
        $this->reputationRepository->lockIntegrity($user, $stakeAmount);
    }

    private function unlockIntegrityStake(User $user, float $stakeAmount): void
    {
        $this->reputationRepository->unlockIntegrity($user, $stakeAmount);
    }

    private function grantIntegrityBonus(User $user, float $bonusAmount): void
    {
        $this->reputationRepository->grantIntegrity($user, $bonusAmount, 'vouch_bonus');
    }

    private function forfeitIntegrityStake(User $user, float $stakeAmount): void
    {
        $this->reputationRepository->deductIntegrity($user, $stakeAmount, 'vouch_forfeit');
    }

    private function getTotalLockedStakes(User $user): float
    {
        return $this->vouchRepository->getTotalLockedStakes($user);
    }

    private function applyVouchImpact(User $vouchee, float $stakeAmount, Context $context): void
    {
        // Apply immediate reputation boost to vouchee based on stake amount
        $impactAmount = $stakeAmount * 0.1; // 10% of stake as immediate boost
        $this->reputationRepository->grantContextualReputation($vouchee, $context, $impactAmount, 'vouch_received');
    }

    private function grantDenizenBonus(User $user, Context $context): void
    {
        // Grant bonus reputation for achieving denizen status
        $this->reputationRepository->grantContextualReputation($user, $context, 10.0, 'denizen_achievement');
    }

    private function applyVouchFailurePenalty(User $user, Context $context): void
    {
        // Apply penalty for failing to achieve denizen status
        $this->reputationRepository->deductContextualReputation($user, $context, 5.0, 'vouch_failure');
    }
}
```

## Supporting Value Objects

```php
<?php

namespace App\ValueObjects;

use Carbon\Carbon;

class IntegrityScore
{
    public function __construct(
        public readonly float $score,
        public readonly ?Context $context = null,
        public readonly Carbon $lastUpdated,
        public readonly ?float $baseIntegrity = null,
        public readonly ?float $contextualMultiplier = null,
        public readonly bool $erosionApplied = false,
        public readonly ?int $daysInactive = null,
        public readonly ?float $inertiaCoefficient = null
    ) {}

    public function getScore(): float
    {
        return $this->score;
    }

    public function isAboveThreshold(float $threshold): bool
    {
        return $this->score >= $threshold;
    }

    public function hasErosion(): bool
    {
        return $this->erosionApplied;
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'context' => $this->context?->getType(),
            'last_updated' => $this->lastUpdated->toISOString(),
            'base_integrity' => $this->baseIntegrity,
            'contextual_multiplier' => $this->contextualMultiplier,
            'erosion_applied' => $this->erosionApplied,
            'days_inactive' => $this->daysInactive,
            'inertia_coefficient' => $this->inertiaCoefficient,
        ];
    }
}
```

```php
<?php

namespace App\ValueObjects;

class Context
{
    public function __construct(
        public readonly string $type,
        public readonly string $id
    ) {}

    public static function village(Village $village): self
    {
        return new self('village', $village->id);
    }

    public static function global(): self
    {
        return new self('global', 'global');
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function equals(Context $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
```

## Repository Interfaces

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use App\ValueObjects\Context;
use App\ValueObjects\ActivityPattern;

interface ReputationRepositoryInterface
{
    public function getBaseIntegrity(User $user): float;
    public function getContextualReputation(User $user, Context $context): ContextualReputationData;
    public function getLastActivityDate(User $user): Carbon;
    public function getActivityPattern(User $user): ActivityPattern;
    public function lockIntegrity(User $user, float $amount): void;
    public function unlockIntegrity(User $user, float $amount): void;
    public function grantIntegrity(User $user, float $amount, string $reason): void;
    public function deductIntegrity(User $user, float $amount, string $reason): void;
    public function grantContextualReputation(User $user, Context $context, float $amount, string $reason): void;
    public function deductContextualReputation(User $user, Context $context, float $amount, string $reason): void;
}
```

## Usage Examples

```php
// Calculate user's integrity score in a specific village
$context = Context::village($village);
$integrityScore = $reputationService->calculateIntegrityScore($user, $context);

// Vouch for a stranger to become a sojourner
$vouch = $reputationService->vouch($voucher, $stranger, 10.0, $context);

// Resolve vouch when stranger becomes denizen
$reputationService->resolveVouch($vouch, true); // Returns stake + 20% bonus

// Check if user can vouch with specified amount
$canVouch = $reputationService->canVouch($user, 15.0);

// Get all locked stakes for a user
$lockedStakes = $reputationService->getLockedStakes($user);
```

This implementation provides a robust, mathematically transparent reputation system that integrates seamlessly with The Village's role progression and P2P trust mechanisms.
