# Technical Proposal: Reputation & Trust System for The Village

## Executive Summary

This proposal outlines a comprehensive reputation and trust scoring system that serves as the foundation for The Village's social hierarchy and P2P resource sharing. The system implements context-based reputation calculations, vouching mechanisms, and role progression while maintaining Clean Architecture principles.

## System Architecture

### 1. Reputation Scoring Algorithm

**Technology Choice**: Weighted Multi-Factor Reputation Model with Temporal Decay

**Reasoning**:
- **Multi-factor approach** captures complex social dynamics better than single-score systems
- **Context-aware scoring** allows different reputation values for village vs global contexts
- **Temporal decay** prevents reputation hoarding and encourages ongoing participation
- **Mathematical transparency** enables users to understand their reputation calculations

**Core Algorithm**:
```
Reputation(context, user) = BaseIntegrity × ActivityFactor × TrustWeight × TimeDecay

Where:
- BaseIntegrity: 0-100 baseline from verified actions
- ActivityFactor: 0.5-2.0 multiplier based on recent participation
- TrustWeight: 0.8-1.2 multiplier from vouching/betrayal history
- TimeDecay: e^(-daysSinceLastActivity/30) for natural erosion
```

### 2. Role Progression System

**Technology Choice**: State Machine with Threshold-Based Transitions

**Reasoning**:
- **Deterministic progression** ensures predictable role advancement
- **Threshold-based** prevents rapid role exploitation
- **State machine pattern** provides clear transition rules and rollback mechanisms
- **Audit trail** enables reputation history tracking

**Role Hierarchy & Thresholds**:
```php
enum Role: string {
    case STRANGER = 'stranger';      // 0-10 integrity
    case SOJOURNER = 'sojourner';    // 11-30 integrity, requires vouch
    case DENIZEN = 'denizen';        // 31-60 integrity, 30 days activity
    case STEWARD = 'steward';        // 61-80 integrity, denizen for 90 days
    case ELDER = 'elder';            // 81-95 integrity, steward for 180 days
    case REEVE = 'reeve';            // 96-99 integrity, successful village creation
    case HIGH_REEVE = 'high_reeve';  // 100 integrity, multiple successful villages
}
```

### 3. Vouching System

**Technology Choice**: Cryptographic Stake-Based Vouching

**Reasoning**:
- **Stake-based vouching** prevents frivolous endorsements through integrity cost
- **Cryptographic proof** enables verifiable vouching without revealing private data
- **Bonus/penalty system** incentivizes careful vouching decisions
- **Reputation recovery** mechanism allows redemption after failed vouches

**Vouching Logic**:
```php
class VouchingService implements VouchingInterface
{
    public function createVouch(User $vouchee, User $voucher, int $stakeAmount): Vouch
    {
        // Deduct integrity stake from voucher
        // Create cryptographic proof of vouch
        // Set maturity period for stake recovery
    }
    
    public function resolveVouch(Vouch $vouch, bool $successful): void
    {
        if ($successful) {
            // Return stake + 20% bonus to voucher
            // Boost vouchee reputation
        } else {
            // Forfeit stake, penalize voucher reputation
        }
    }
}
```

### 4. Context-Based Reputation

**Technology Choice**: Multi-Dimensional Reputation Vector

**Reasoning**:
- **Context separation** prevents reputation bleed between different social spheres
- **Vector-based approach** allows efficient computation of multiple contexts
- **Weighted aggregation** enables global reputation calculation from local contexts
- **Privacy preservation** keeps village-specific reputation local to that village

**Context Implementation**:
```dart
class ReputationVector {
  final Map<Context, double> contextScores;
  final double globalIntegrity;
  
  double getReputation(Context context) {
    return contextScores[context] ?? 0.0;
  }
  
  void updateReputation(Context context, double delta) {
    contextScores[context] = (contextScores[context] ?? 0.0) + delta;
    _recalculateGlobalIntegrity();
  }
}
```

## Clean Architecture Implementation

### 3. Reputation Scoring Algorithm

**Mathematical Foundation**:
```php
// Reputation Calculation Formula
$reputationScore = (
    $baseIntegrity * $inertiaCoefficient +
    sum($contextualScores * $contextWeights) +
    $vouchingBonus - $penaltyTotal
) * $timeDecayFactor

// Where:
$baseIntegrity = user's staked integrity score
$inertiaCoefficient = activity-based multiplier (0.5 to 2.0)
$contextualScores = [village, map, global, resource, document, asset] reputation scores
$contextWeights = [0.35, 0.25, 0.25, 0.30, 0.20, 0.15] for different contexts
$vouchingBonus = sum(successfulVouches * 0.20)
$penaltyTotal = sum(breaches * penalty) + sum(betrayals * severePenalty)
$timeDecayFactor = exp(-DECAY_RATE * daysSinceLastActivity)
```

**Implementation Strategy**:
```php
// app/Services/ReputationCalculationService.php
class ReputationCalculationService {
    public function calculateUserReputation(User $user): ReputationScore {
        $baseScore = $this->calculateBaseIntegrity($user);
        $activityFactor = $this->calculateActivityFactor($user);
        $trustWeight = $this->calculateTrustWeight($user);
        $timeDecay = $this->calculateTimeDecay($user);
        
        return new ReputationScore(
            $baseIntegrity * $activityFactor * $trustWeight * $timeDecay,
            $context,
            now()
        );
    }
    
    public function calculateResourceReputation(Resource $resource): ReputationScore {
        // Calculate reputation for shared resources based on usage and feedback
        $usageScore = $this->calculateResourceUsageScore($resource);
        $feedbackScore = $this->calculateResourceFeedbackScore($resource);
        $creatorReputation = $this->getResourceCreatorReputation($resource);
        $resourceReputation = $this->calculateResourceTrustworthiness($resource);
        
        // Resource reputation contributes to creator's overall score
        return new ReputationScore(
            resource: $resource->id,
            usage: $usageScore,
            feedback: $feedbackScore,
            creator: $creatorReputation,
            trustworthiness: $resourceReputation,
            total: ($usageScore + $feedbackScore) * 0.3 // 30% weight for resource reputation
        );
    }
    
    public function calculateDocumentReputation(Document $document): ReputationScore {
        // Calculate reputation for shared documents and media
        $qualityScore = $this->calculateDocumentQuality($document);
        $sharingScore = $this->calculateDocumentSharingScore($document);
        $authorReputation = $this->getDocumentAuthorReputation($document);
        $documentReputation = $this->calculateDocumentTrustworthiness($document);
        
        return new ReputationScore(
            document: $document->id,
            quality: $qualityScore,
            sharing: $sharingScore,
            author: $authorReputation,
            trustworthiness: $documentReputation,
            total: ($qualityScore + $sharingScore) * 0.2 // 20% weight for document reputation
        );
    }
    
    public function calculateAssetReputation(Asset $asset): ReputationScore {
        // Calculate reputation for media assets, code snippets, tools
        $qualityScore = $this->calculateAssetQuality($asset);
        $usageScore = $this->calculateAssetUsageScore($asset);
        $creatorReputation = $this->getAssetCreatorReputation($asset);
        $assetReputation = $this->calculateAssetTrustworthiness($asset);
        
        return new ReputationScore(
            asset: $asset->id,
            quality: $qualityScore,
            usage: $usageScore,
            creator: $creatorReputation,
            trustworthiness: $assetReputation,
            total: ($qualityScore + $usageScore) * 0.15 // 15% weight for asset reputation
        );
    }
    
    public function calculateResourceTrustworthiness(Resource $resource): float {
        // Calculate resource trustworthiness based on creator reputation and usage patterns
        $creatorReputation = $this->getResourceCreatorReputation($resource);
        $usageConsistency = $this->calculateUsageConsistency($resource);
        $feedbackQuality = $this->calculateFeedbackQuality($resource);
        
        // Trustworthiness score (0.0 to 1.0)
        return ($creatorReputation * 0.4) + 
               ($usageConsistency * 0.3) + 
               ($feedbackQuality * 0.3);
    }
    
    public function calculateDocumentTrustworthiness(Document $document): float {
        // Calculate document trustworthiness based on author reputation and sharing patterns
        $authorReputation = $this->getDocumentAuthorReputation($document);
        $sharingFrequency = $this->calculateSharingFrequency($document);
        $contentQuality = $this->calculateContentQuality($document);
        
        // Trustworthiness score (0.0 to 1.0)
        return ($authorReputation * 0.5) + 
               ($sharingFrequency * 0.3) + 
               ($contentQuality * 0.2);
    }
    
    public function calculateAssetTrustworthiness(Asset $asset): float {
        // Calculate asset trustworthiness based on creator reputation and usage patterns
        $creatorReputation = $this->getAssetCreatorReputation($asset);
        $usageEfficiency = $this->calculateUsageEfficiency($asset);
        $codeQuality = $this->calculateCodeQuality($asset);
        
        // Trustworthiness score (0.0 to 1.0)
        return ($creatorReputation * 0.6) + 
               ($usageEfficiency * 0.2) + 
               ($codeQuality * 0.2);
    }
}
```

### Backend (Laravel)

**Domain Layer**:
```php
// app/Domain/ValueObjects/ReputationScore.php
class ReputationScore
{
    public function __construct(
        private readonly float $score,
        private readonly Context $context,
        private readonly Carbon $lastUpdated
    ) {}
    
    public function applyDecay(int $days): self
    public function isAboveThreshold(Role $requiredRole): bool
}
```

**Application Layer**:
```php
// app/Services/ReputationCalculationService.php
class ReputationCalculationService {
    public function calculateUserReputation(User $user): ReputationScore {
        $baseIntegrity = $this->calculateBaseIntegrity($user);
        $activityFactor = $this->calculateActivityFactor($user);
        $trustWeight = $this->calculateTrustWeight($user);
        $timeDecay = $this->calculateTimeDecay($user);
        
        return new ReputationScore(
            $baseIntegrity * $activityFactor * $trustWeight * $timeDecay,
            $context,
            now()
        );
    }
    
    public function calculateResourceReputation(Resource $resource): ReputationScore {
        // Calculate reputation for shared resources based on usage and feedback
        $usageScore = $this->calculateResourceUsageScore($resource);
        $feedbackScore = $this->calculateResourceFeedbackScore($resource);
        $creatorReputation = $this->getResourceCreatorReputation($resource);
        
        // Resource reputation contributes to creator's overall score
        return new ReputationScore(
            resource: $resource->id,
            usage: $usageScore,
            feedback: $feedbackScore,
            creator: $creatorReputation,
            total: ($usageScore + $feedbackScore) * 0.3 // 30% weight for resource reputation
        );
    }
    
    public function calculateDocumentReputation(Document $document): ReputationScore {
        // Calculate reputation for shared documents and media
        $qualityScore = $this->calculateDocumentQuality($document);
        $sharingScore = $this->calculateDocumentSharingScore($document);
        $authorReputation = $this->getDocumentAuthorReputation($document);
        
        return new ReputationScore(
            document: $document->id,
            quality: $qualityScore,
            sharing: $sharingScore,
            author: $authorReputation,
            total: ($qualityScore + $sharingScore) * 0.2 // 20% weight for document reputation
        );
    }
    
    public function calculateAssetReputation(Asset $asset): ReputationScore {
        // Calculate reputation for media assets, code snippets, tools
        $qualityScore = $this->calculateAssetQuality($asset);
        $usageScore = $this->calculateAssetUsageScore($asset);
        $creatorReputation = $this->getAssetCreatorReputation($asset);
        
        return new ReputationScore(
            asset: $asset->id,
            quality: $qualityScore,
            usage: $usageScore,
            creator: $creatorReputation,
            total: ($qualityScore + $usageScore) * 0.15 // 15% weight for asset reputation
        );
    }
}
```

**Infrastructure Layer**:
```php
// app/Repositories/ReputationRepository.php
class ReputationRepository implements ReputationRepositoryInterface
{
    public function save(ReputationScore $score): void
    public function findByUserAndContext(User $user, Context $context): ?ReputationScore
    public function getReputationHistory(User $user, int $days): Collection
}
```

### Frontend (Dart/Wasm)

**Domain Layer**:
```dart
// lib/domain/entities/reputation.dart
class Reputation {
  final String userId;
  final Map<Context, double> contextScores;
  final Role currentRole;
  final List<Vouch> activeVouches;
  
  bool canVouchFor(User user) {
    return currentRole.index >= Role.DENIZEN.index && 
           contextScores[Context.VILLAGE]! >= 50.0;
  }
}
```

**Application Layer**:
```dart
// lib/application/use_cases/calculate_reputation.dart
class CalculateReputationUseCase {
  Future<ReputationScore> execute(User user, Context context) async {
    // Business logic for reputation calculation
  }
}
```

## Performance Considerations

### Scalability Targets
- **Reputation calculations**: <100ms per user context
- **Role transitions**: <50ms validation time
- **Vouch resolution**: Batch processing every 5 minutes
- **Context updates**: Real-time propagation within 1 second

### Optimization Strategies
1. **Incremental updates** for reputation scores (recalculate only changed factors)
2. **Batch processing** for time-based decay calculations
3. **Caching layer** for frequently accessed reputation data
4. **Background jobs** for complex reputation recalculations

## Security Architecture

### Threat Mitigation
- **Reputation manipulation**: Prevented through cryptographic audit trails
- **Sybil attacks**: Mitigated by vouching costs and role requirements
- **Reputation farming**: Throttled through activity factor limits
- **Vouching collusion**: Detected through graph analysis of vouching patterns

### Privacy Protection
- **Context isolation**: Reputation data separated by social context
- **Differential privacy**: Noise added to reputation queries for anonymity
- **Data minimization**: Only essential reputation data stored and transmitted
- **User control**: Explicit consent for reputation sharing between contexts

## Integration Points

### P2P Network Integration
- **Trust-based peer selection** using reputation scores
- **Resource sharing permissions** based on role hierarchy
- **Network behavior monitoring** through reputation feedback loops
- **Server-level trust relationships** extending reputation system to peer nodes
- **Cross-server reputation aggregation** for distributed trust calculations
- **Peer-to-peer reputation verification** using cryptographic proofs
- **Server reputation scoring** based on uptime, reliability, and user satisfaction

### 3D UI Integration
- **Reputation visualization** through avatar appearance and effects
- **Role-based UI permissions** for different village spaces
- **Trust indicators** for peer interactions and resource sharing

## Implementation Timeline

**Phase 1** (2 weeks): Core reputation calculation engine + role system
**Phase 2** (2 weeks): Vouching system + cryptographic proofs
**Phase 3** (1 week): Context-based reputation + privacy controls
**Phase 4** (1 week): P2P integration + performance optimization
**Phase 5** (1 week): Security audit + UI integration

This reputation system provides the social foundation for The Village's trust-based community while maintaining mathematical transparency, privacy protection, and Clean Architecture principles.

---

**Document Created**: May 9, 2026  
**Author**: AI Assistant (Cascade)  
**Project**: The Village - Reputation & Trust System  
**Version**: 1.0
