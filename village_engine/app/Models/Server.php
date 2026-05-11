<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Server Model
 * 
 * Represents a village server instance
 */
class Server extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'domain',
        'max_population',
        'description',
        'admin_id',
        'status',
        'settings',
        'ip_address',
        'port',
        'version',
    ];
    
    protected $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get villages on this server
     */
    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }
    
    /**
     * Get the server admin
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
    
    /**
     * Get total population across all villages
     */
    public function getTotalPopulationAttribute(): int
    {
        return $this->villages()->sum('current_population');
    }
    
    /**
     * Get total capacity across all villages
     */
    public function getTotalCapacityAttribute(): int
    {
        return $this->villages()->sum('max_population');
    }
    
    /**
     * Get average reputation across all villages
     */
    public function getAverageReputationAttribute(): float
    {
        return $this->villages()->avg('reputation_required');
    }
    
    /**
     * Check if server is at capacity
     */
    public function isAtCapacity(): bool
    {
        return $this->getTotalPopulationAttribute() >= $this->getTotalCapacityAttribute();
    }
}
