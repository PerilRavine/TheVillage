<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVillage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'village_id',
        'role',
        'status',
        'joined_at',
        'role_changed_at',
        'last_activity_at',
        'left_at',
        'vouched_by',
        'vouch_id',
        'join_reason',
        'interactions_count',
        'resources_shared_count',
        'moderation_actions_count',
        'contribution_score',
        'permissions',
        'can_invite_others',
        'can_moderate',
        'can_manage_resources',
        'privacy_settings',
        'profile_visible_to_village',
        'activity_visible_to_village',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'role_changed_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'left_at' => 'datetime',
        'permissions' => 'array',
        'privacy_settings' => 'array',
        'can_invite_others' => 'boolean',
        'can_moderate' => 'boolean',
        'can_manage_resources' => 'boolean',
        'profile_visible_to_village' => 'boolean',
        'activity_visible_to_village' => 'boolean',
    ];

    // Status types
    const STATUS_INVITED = 'invited';
    const STATUS_JOINED = 'joined';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_LEFT = 'left';
    const STATUS_EXPELLED = 'expelled';

    // Role types
    const ROLE_STRANGER = 'stranger';
    const ROLE_SOJOURNER = 'sojourner';
    const ROLE_DENIZEN = 'denizen';
    const ROLE_STEWARD = 'steward';
    const ROLE_ELDER = 'elder';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vouched_by');
    }

    public function vouch(): BelongsTo
    {
        return $this->belongsTo(Vouch::class, 'vouch_id');
    }

    // Helper methods
    public function isInvited(): bool
    {
        return $this->status === self::STATUS_INVITED;
    }

    public function isJoined(): bool
    {
        return $this->status === self::STATUS_JOINED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isLeft(): bool
    {
        return $this->status === self::STATUS_LEFT;
    }

    public function isExpelled(): bool
    {
        return $this->status === self::STATUS_EXPELLED;
    }

    public function isStranger(): bool
    {
        return $this->role === self::ROLE_STRANGER;
    }

    public function isSojourner(): bool
    {
        return $this->role === self::ROLE_SOJOURNER;
    }

    public function isDenizen(): bool
    {
        return $this->role === self::ROLE_DENIZEN;
    }

    public function isSteward(): bool
    {
        return $this->role === self::ROLE_STEWARD;
    }

    public function isElder(): bool
    {
        return $this->role === self::ROLE_ELDER;
    }

    public function isDenizenOrHigher(): bool
    {
        return in_array($this->role, [
            self::ROLE_DENIZEN,
            self::ROLE_STEWARD,
            self::ROLE_ELDER,
        ]);
    }

    public function isStewardOrHigher(): bool
    {
        return in_array($this->role, [
            self::ROLE_STEWARD,
            self::ROLE_ELDER,
        ]);
    }

    public function isActive(): bool
    {
        return $this->isJoined() && !$this->isSuspended();
    }

    public function canInteract(): bool
    {
        return $this->isActive() && $this->isSojournerOrHigher();
    }

    public function isSojournerOrHigher(): bool
    {
        return in_array($this->role, [
            self::ROLE_SOJOURNER,
            self::ROLE_DENIZEN,
            self::ROLE_STEWARD,
            self::ROLE_ELDER,
        ]);
    }

    public function canModerate(): bool
    {
        return $this->isActive() && $this->isStewardOrHigher();
    }

    public function canManageResources(): bool
    {
        return $this->isActive() && $this->can_manage_resources;
    }

    public function canInviteOthers(): bool
    {
        return $this->isActive() && $this->can_invite_others;
    }

    public function promoteToRole(string $newRole): void
    {
        $this->update([
            'role' => $newRole,
            'role_changed_at' => now(),
        ]);

        // Update contextual reputation
        $contextualReputation = $this->user->getContextualReputation(
            ContextualReputation::CONTEXT_VILLAGE,
            $this->village_id
        );

        if ($contextualReputation) {
            $contextualReputation->update(['role_in_context' => $newRole]);
        }
    }

    public function suspend(string $reason = null): void
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);

        // Record suspension transaction
        ReputationTransaction::create([
            'user_id' => $this->user_id,
            'transaction_type' => ReputationTransaction::TYPE_ROLE_CHANGE,
            'context_type' => ContextualReputation::CONTEXT_VILLAGE,
            'context_id' => $this->village_id,
            'amount' => -5.0, // Penalty for suspension
            'balance_before' => $this->user->getTotalIntegrity(),
            'balance_after' => $this->user->getTotalIntegrity() - 5.0,
            'village_id' => $this->village_id,
            'reason' => 'Village suspension',
            'metadata' => [
                'previous_status' => $this->getOriginal('status'),
                'suspension_reason' => $reason,
            ],
        ]);
    }

    public function unsuspend(): void
    {
        $this->update(['status' => self::STATUS_JOINED]);

        // Record unsuspension transaction
        ReputationTransaction::create([
            'user_id' => $this->user_id,
            'transaction_type' => ReputationTransaction::TYPE_ROLE_CHANGE,
            'context_type' => ContextualReputation::CONTEXT_VILLAGE,
            'context_id' => $this->village_id,
            'amount' => 2.0, // Small bonus for unsuspension
            'balance_before' => $this->user->getTotalIntegrity(),
            'balance_after' => $this->user->getTotalIntegrity() + 2.0,
            'village_id' => $this->village_id,
            'reason' => 'Village unsuspension',
            'metadata' => [
                'previous_status' => $this->getOriginal('status'),
            ],
        ]);
    }

    public function leave(): void
    {
        $this->update([
            'status' => self::STATUS_LEFT,
            'left_at' => now(),
        ]);

        // Update village population
        $this->village->updatePopulation();
    }

    public function expel(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_EXPELLED,
            'left_at' => now(),
        ]);

        // Record expulsion transaction
        ReputationTransaction::create([
            'user_id' => $this->user_id,
            'transaction_type' => ReputationTransaction::TYPE_BREACH_COMMITTED,
            'context_type' => ContextualReputation::CONTEXT_VILLAGE,
            'context_id' => $this->village_id,
            'amount' => -15.0, // Significant penalty for expulsion
            'balance_before' => $this->user->getTotalIntegrity(),
            'balance_after' => $this->user->getTotalIntegrity() - 15.0,
            'village_id' => $this->village_id,
            'reason' => 'Expelled from village',
            'metadata' => [
                'expulsion_reason' => $reason,
                'previous_role' => $this->role,
            ],
        ]);

        // Update village population
        $this->village->updatePopulation();
    }

    public function recordInteraction(): void
    {
        $this->increment('interactions_count');
        $this->update(['last_activity_at' => now()]);
        $this->user->updateLastActivity();

        // Update contextual reputation
        $contextualReputation = $this->user->getContextualReputation(
            ContextualReputation::CONTEXT_VILLAGE,
            $this->village_id
        );

        if ($contextualReputation) {
            $contextualReputation->recordInteraction();
        }
    }

    public function recordResourceShare(): void
    {
        $this->increment('resources_shared_count');
        $this->recordInteraction();
        $this->increment('contribution_score', 1.0);
    }

    public function recordModerationAction(): void
    {
        if ($this->canModerate()) {
            $this->increment('moderation_actions_count');
            $this->increment('contribution_score', 0.5);
        }
    }

    public function updateContributionScore(): void
    {
        // Calculate contribution score based on various activities
        $interactionWeight = $this->interactions_count * 0.1;
        $resourceWeight = $this->resources_shared_count * 0.5;
        $moderationWeight = $this->moderation_actions_count * 1.0;

        $this->update([
            'contribution_score' => $interactionWeight + $resourceWeight + $moderationWeight,
        ]);
    }

    public function getDaysInVillage(): int
    {
        if (!$this->joined_at) {
            return 0;
        }

        $endDate = $this->left_at ?? now();
        return $this->joined_at->diffInDays($endDate);
    }

    public function getPrivacySetting(string $key, $default = null)
    {
        $settings = $this->privacy_settings ?? [];
        return $settings[$key] ?? $default;
    }

    public function setPrivacySetting(string $key, $value): void
    {
        $settings = $this->privacy_settings ?? [];
        $settings[$key] = $value;
        $this->update(['privacy_settings' => $settings]);
    }

    public function hasPermission(string $permission): bool
    {
        // Check explicit permissions
        if (isset($this->permissions[$permission])) {
            return $this->permissions[$permission];
        }

        // Check role-based permissions
        switch ($this->role) {
            case self::ROLE_ELDER:
                return true; // Elders have all permissions
            case self::ROLE_STEWARD:
                return in_array($permission, [
                    'moderate_discussions',
                    'manage_resources',
                    'view_reports',
                ]);
            case self::ROLE_DENIZEN:
                return in_array($permission, [
                    'participate_discussions',
                    'share_resources',
                    'invite_users',
                ]);
            case self::ROLE_SOJOURNER:
                return in_array($permission, [
                    'participate_discussions',
                    'view_resources',
                ]);
            default:
                return false;
        }
    }

    // Scopes
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForVillage($query, Village $village)
    {
        return $query->where('village_id', $village->id);
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_JOINED);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    public function scopeLeft($query)
    {
        return $query->whereIn('status', [self::STATUS_LEFT, self::STATUS_EXPELLED]);
    }

    public function scopeDenizens($query)
    {
        return $query->where('role', self::ROLE_DENIZEN)->active();
    }

    public function scopeStewards($query)
    {
        return $query->where('role', self::ROLE_STEWARD)->active();
    }

    public function scopeElders($query)
    {
        return $query->where('role', self::ROLE_ELDER)->active();
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('last_activity_at', '>=', now()->subDays($days));
    }

    public function scopeSortedByContribution($query)
    {
        return $query->orderBy('contribution_score', 'desc');
    }

    public function scopeSortedByActivity($query)
    {
        return $query->orderBy('last_activity_at', 'desc');
    }
}
