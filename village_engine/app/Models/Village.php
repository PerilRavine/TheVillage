<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Village extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'theme',
        'settings',
        'reputation_score',
        'population',
        'max_population',
        'status',
        'founded_at',
        'last_activity_at',
        'map_x',
        'map_y',
        'map_z',
        'elder_id',
        'created_by',
        'public_access',
        'minimum_reputation_to_join',
        'identity_verification_required',
    ];

    protected $casts = [
        'settings' => 'array',
        'reputation_score' => 'decimal:4',
        'founded_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'map_x' => 'decimal:4',
        'map_y' => 'decimal:4',
        'map_z' => 'decimal:4',
        'public_access' => 'boolean',
        'minimum_reputation_to_join' => 'decimal:4',
        'identity_verification_required' => 'boolean',
    ];

    // Relationships
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_villages')
            ->withPivot([
                'role', 'status', 'joined_at', 'role_changed_at', 
                'last_activity_at', 'left_at', 'vouched_by', 'vouch_id',
                'join_reason', 'interactions_count', 'resources_shared_count',
                'moderation_actions_count', 'contribution_score', 'permissions',
                'can_invite_others', 'can_moderate', 'can_manage_resources',
                'privacy_settings', 'profile_visible_to_village', 'activity_visible_to_village'
            ])
            ->withTimestamps();
    }

    public function denizens(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'denizen')->wherePivot('status', 'joined');
    }

    public function stewards(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'steward')->wherePivot('status', 'joined');
    }

    public function sojourners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'sojourner')->wherePivot('status', 'joined');
    }

    public function strangers(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'stranger')->wherePivot('status', 'joined');
    }

    public function elder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'elder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(VillageRelationship::class, 'village_1_id')
            ->orWhere('village_2_id', $this->id);
    }

    public function reputationTransactions(): HasMany
    {
        return $this->hasMany(ReputationTransaction::class);
    }

    public function contextualReputations(): HasMany
    {
        return $this->hasMany(ContextualReputation::class)
            ->where('context_type', 'village')
            ->where('context_id', $this->id);
    }

    public function vouches(): HasMany
    {
        return $this->hasMany(Vouch::class)
            ->where('context_type', 'village')
            ->where('context_id', $this->id);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isForming(): bool
    {
        return $this->status === 'forming';
    }

    public function isFull(): bool
    {
        return $this->population >= $this->max_population;
    }

    public function canAcceptNewMembers(): bool
    {
        return $this->isActive() && !$this->isFull();
    }

    public function getActiveMemberCount(): int
    {
        return $this->users()->wherePivot('status', 'joined')->count();
    }

    public function getDenizenCount(): int
    {
        return $this->denizens()->count();
    }

    public function getRoleDistribution(): array
    {
        return [
            'stranger' => $this->strangers()->count(),
            'sojourner' => $this->sojourners()->count(),
            'denizen' => $this->denizens()->count(),
            'steward' => $this->stewards()->count(),
            'elder' => $this->elder ? 1 : 0,
        ];
    }

    public function getAverageReputation(): float
    {
        return $this->contextualReputations()->avg('reputation_score') ?? 0.0;
    }

    public function getRelationshipWith(Village $otherVillage): ?VillageRelationship
    {
        return $this->relationships()
            ->where(function ($query) use ($otherVillage) {
                $query->where('village_1_id', $otherVillage->id)
                      ->orWhere('village_2_id', $otherVillage->id);
            })
            ->first();
    }

    public function updatePopulation(): void
    {
        $this->update(['population' => $this->getActiveMemberCount()]);
    }

    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function addMember(User $user, string $role = 'stranger', ?User $vouchedBy = null): void
    {
        $this->users()->attach($user->id, [
            'role' => $role,
            'status' => 'joined',
            'joined_at' => now(),
            'vouched_by' => $vouchedBy?->id,
        ]);

        $this->updatePopulation();
        $this->updateLastActivity();
    }

    public function removeMember(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'status' => 'left',
            'left_at' => now(),
        ]);

        $this->updatePopulation();
    }

    public function promoteMember(User $user, string $newRole): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'role' => $newRole,
            'role_changed_at' => now(),
        ]);
    }

    public function canUserJoin(User $user): bool
    {
        if (!$this->canAcceptNewMembers()) {
            return false;
        }

        if ($user->getTotalIntegrity() < $this->minimum_reputation_to_join) {
            return false;
        }

        if ($this->identity_verification_required && !$user->identity_verified) {
            return false;
        }

        return true;
    }

    public function getMapPosition(): array
    {
        return [
            'x' => (float) $this->map_x,
            'y' => (float) $this->map_y,
            'z' => (float) $this->map_z,
        ];
    }

    public function setMapPosition(float $x, float $y, float $z): void
    {
        $this->update([
            'map_x' => $x,
            'map_y' => $y,
            'map_z' => $z,
        ]);
    }
}
