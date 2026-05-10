<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VillageRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_1_id',
        'village_2_id',
        'strength',
        'relationship_type',
        'resource_shares_count',
        'user_migrations_count',
        'trade_volume',
        'trust_level',
        'reputation_transfer_weight',
        'established_at',
        'last_interaction_at',
        'days_since_interaction',
        'strength_change_rate',
        'interaction_history',
        'rest_length',
        'spring_constant',
    ];

    protected $casts = [
        'strength' => 'decimal:4',
        'trade_volume' => 'decimal:2',
        'trust_level' => 'decimal:4',
        'reputation_transfer_weight' => 'decimal:4',
        'established_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'interaction_history' => 'array',
        'rest_length' => 'decimal:4',
        'spring_constant' => 'decimal:4',
    ];

    // Relationship types
    const TYPE_ALLIED = 'allied';
    const TYPE_NEUTRAL = 'neutral';
    const TYPE_COMPETITIVE = 'competitive';
    const TYPE_HOSTILE = 'hostile';

    // Relationships
    public function village1(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_1_id');
    }

    public function village2(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_2_id');
    }

    // Helper methods
    public function isAllied(): bool
    {
        return $this->relationship_type === self::TYPE_ALLIED;
    }

    public function isNeutral(): bool
    {
        return $this->relationship_type === self::TYPE_NEUTRAL;
    }

    public function isCompetitive(): bool
    {
        return $this->relationship_type === self::TYPE_COMPETITIVE;
    }

    public function isHostile(): bool
    {
        return $this->relationship_type === self::TYPE_HOSTILE;
    }

    public function getOtherVillageId(int $currentVillageId): int
    {
        return $this->village_1_id === $currentVillageId ? $this->village_2_id : $this->village_1_id;
    }

    public function getOtherVillage(int $currentVillageId): ?Village
    {
        $otherId = $this->getOtherVillageId($currentVillageId);
        return $this->village_1_id === $currentVillageId ? $this->village2 : $this->village1;
    }

    public function updateStrength(float $newStrength): void
    {
        $oldStrength = $this->strength;
        $this->strength = max(0.0, min(1.0, $newStrength));
        $this->strength_change_rate = $this->strength - $oldStrength;
        
        // Update rest length based on strength (stronger relationships = shorter rest length)
        $this->rest_length = 100.0 * (1.0 - $this->strength);
        
        $this->save();
    }

    public function recordResourceShare(): void
    {
        $this->increment('resource_shares_count');
        $this->updateLastInteraction();
        $this->updateTrustLevel();
    }

    public function recordUserMigration(): void
    {
        $this->increment('user_migrations_count');
        $this->updateLastInteraction();
        $this->updateTrustLevel();
    }

    public function recordTrade(float $volume): void
    {
        $this->increment('trade_volume', $volume);
        $this->updateLastInteraction();
        $this->updateTrustLevel();
    }

    public function updateLastInteraction(): void
    {
        $this->update([
            'last_interaction_at' => now(),
            'days_since_interaction' => 0,
        ]);
    }

    public function updateTrustLevel(): void
    {
        // Calculate trust level based on multiple factors
        $baseTrust = $this->strength;
        $resourceBonus = min($this->resource_shares_count / 10.0, 0.2);
        $migrationBonus = min($this->user_migrations_count / 5.0, 0.2);
        $tradeBonus = min($this->trade_volume / 1000.0, 0.2);
        $recentActivityBonus = max(0, 1.0 - ($this->days_since_interaction / 365.0)) * 0.1;

        $this->trust_level = max(0.0, min(1.0, 
            $baseTrust + $resourceBonus + $migrationBonus + $tradeBonus + $recentActivityBonus
        ));

        $this->save();
    }

    public function calculateForceVector(Village $currentVillage, Village $otherVillage): array
    {
        // Calculate force vector for force-directed graph
        $direction = [
            $otherVillage->map_x - $currentVillage->map_x,
            $otherVillage->map_y - $currentVillage->map_y,
            $otherVillage->map_z - $currentVillage->map_z,
        ];

        $distance = sqrt(
            $direction[0] ** 2 + 
            $direction[1] ** 2 + 
            $direction[2] ** 2
        );

        if ($distance < 0.01) {
            return [0, 0, 0]; // Avoid division by zero
        }

        // Normalize direction
        $direction = [
            $direction[0] / $distance,
            $direction[1] / $distance,
            $direction[2] / $distance,
        ];

        // Calculate spring force (Hooke's Law: F = -k * x)
        $displacement = $distance - $this->rest_length;
        $springForce = $this->spring_constant * $displacement;

        // Apply relationship type modifier
        $forceModifier = 1.0;
        switch ($this->relationship_type) {
            case self::TYPE_ALLIED:
                $forceModifier = 0.8; // Stronger attraction
                break;
            case self::TYPE_HOSTILE:
                $forceModifier = 1.5; // Weaker attraction (more repulsion)
                break;
            case self::TYPE_COMPETITIVE:
                $forceModifier = 1.2; // Slightly weaker attraction
                break;
        }

        return [
            $direction[0] * $springForce * $forceModifier,
            $direction[1] * $springForce * $forceModifier,
            $direction[2] * $springForce * $forceModifier,
        ];
    }

    public function getInteractionHistorySummary(): array
    {
        $history = $this->interaction_history ?? [];
        $summary = [
            'total_interactions' => count($history),
            'recent_interactions' => 0,
            'interaction_types' => [],
        ];

        $thirtyDaysAgo = now()->subDays(30);

        foreach ($history as $interaction) {
            if (isset($interaction['timestamp']) && $interaction['timestamp'] > $thirtyDaysAgo) {
                $summary['recent_interactions']++;
            }

            $type = $interaction['type'] ?? 'unknown';
            $summary['interaction_types'][$type] = ($summary['interaction_types'][$type] ?? 0) + 1;
        }

        return $summary;
    }

    public function addInteractionToHistory(string $type, array $metadata = []): void
    {
        $history = $this->interaction_history ?? [];
        
        $history[] = [
            'type' => $type,
            'timestamp' => now()->toISOString(),
            'metadata' => $metadata,
        ];

        // Keep only last 100 interactions to prevent bloat
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        $this->update(['interaction_history' => $history]);
    }

    // Scopes
    public function scopeForVillage($query, Village $village)
    {
        return $query->where(function ($q) use ($village) {
            $q->where('village_1_id', $village->id)
              ->orWhere('village_2_id', $village->id);
        });
    }

    public function scopeBetweenVillages($query, Village $village1, Village $village2)
    {
        return $query->where(function ($q) use ($village1, $village2) {
            $q->where('village_1_id', $village1->id)
              ->where('village_2_id', $village2->id);
        })->orWhere(function ($q) use ($village1, $village2) {
            $q->where('village_1_id', $village2->id)
              ->where('village_2_id', $village1->id);
        });
    }

    public function scopeWithType($query, string $type)
    {
        return $query->where('relationship_type', $type);
    }

    public function scopeStrong($query)
    {
        return $query->where('strength', '>=', 0.7);
    }

    public function scopeWeak($query)
    {
        return $query->where('strength', '<=', 0.3);
    }

    public function scopeActive($query)
    {
        return $query->where('last_interaction_at', '>=', now()->subDays(30));
    }

    public function scopeSortedByStrength($query)
    {
        return $query->orderBy('strength', 'desc');
    }

    public function scopeSortedByTrust($query)
    {
        return $query->orderBy('trust_level', 'desc');
    }
}
