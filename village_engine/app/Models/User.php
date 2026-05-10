<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// use Laravel\Sanctum\HasApiTokens; // Commented out - Sanctum not installed
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'display_name',
        'passkey_credentials',
        'passkey_enabled',
        'role',
        'base_integrity',
        'available_integrity',
        'locked_integrity',
        'identity_verified',
        'verification_method',
        'verified_at',
        'last_activity_at',
        'inertia_coefficient',
        'bio',
        'profile_data',
        'privacy_settings',
        'public_profile',
    ];

    protected $hidden = [
        'password',
        'passkey_credentials',
        'remember_token',
    ];

    protected $casts = [
        'passkey_credentials' => 'array',
        'passkey_enabled' => 'boolean',
        'base_integrity' => 'decimal:4',
        'available_integrity' => 'decimal:4',
        'locked_integrity' => 'decimal:4',
        'identity_verified' => 'boolean',
        'verified_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'inertia_coefficient' => 'decimal:4',
        'profile_data' => 'array',
        'privacy_settings' => 'array',
        'public_profile' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    // Relationships
    public function reputationTransactions(): HasMany
    {
        return $this->hasMany(ReputationTransaction::class);
    }

    public function contextualReputations(): HasMany
    {
        return $this->hasMany(ContextualReputation::class);
    }

    public function vouchesGiven(): HasMany
    {
        return $this->hasMany(Vouch::class, 'voucher_id');
    }

    public function vouchesReceived(): HasMany
    {
        return $this->hasMany(Vouch::class, 'vouchee_id');
    }

    public function villages(): BelongsToMany
    {
        return $this->belongsToMany(Village::class, 'user_villages')
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

    public function ownedVillages(): HasMany
    {
        return $this->hasMany(Village::class, 'created_by');
    }

    public function ledVillages(): HasMany
    {
        return $this->hasMany(Village::class, 'elder_id');
    }

    // Helper methods
    public function canStakeIntegrity(float $amount): bool
    {
        return $this->available_integrity >= $amount && 
               $amount >= 1.0 && 
               $amount <= $this->available_integrity * 0.5;
    }

    public function getTotalIntegrity(): float
    {
        return (float) $this->base_integrity;
    }

    public function getAvailableIntegrity(): float
    {
        return (float) $this->available_integrity;
    }

    public function getLockedIntegrity(): float
    {
        return (float) $this->locked_integrity;
    }

    public function isDenizenOrHigher(): bool
    {
        return in_array($this->role, ['denizen', 'steward', 'elder', 'reeve', 'high_reeve']);
    }

    public function isStewardOrHigher(): bool
    {
        return in_array($this->role, ['steward', 'elder', 'reeve', 'high_reeve']);
    }

    public function isElderOrHigher(): bool
    {
        return in_array($this->role, ['elder', 'reeve', 'high_reeve']);
    }

    public function getRoleInVillage(Village $village): ?string
    {
        $membership = $this->villages()->where('village_id', $village->id)->first();
        return $membership?->pivot->role;
    }

    public function isMemberOfVillage(Village $village): bool
    {
        return $this->villages()->where('village_id', $village->id)->exists();
    }

    public function getContextualReputation(string $contextType, ?int $contextId = null): ?ContextualReputation
    {
        return $this->contextualReputations()
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->first();
    }

    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function lockIntegrity(float $amount): void
    {
        $this->decrement('available_integrity', $amount);
        $this->increment('locked_integrity', $amount);
    }

    public function unlockIntegrity(float $amount): void
    {
        $this->decrement('locked_integrity', $amount);
        $this->increment('available_integrity', $amount);
    }

    public function grantIntegrity(float $amount): void
    {
        $this->increment('base_integrity', $amount);
        $this->increment('available_integrity', $amount);
    }

    public function deductIntegrity(float $amount): void
    {
        $this->decrement('base_integrity', $amount);
        $this->decrement('available_integrity', $amount);
    }
}
