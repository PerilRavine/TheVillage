<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Village;
use App\Models\ReputationScore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    public function run()
    {
        // Create users with different roles and reputation levels
        
        // Strangers (low reputation, no vouches)
        User::factory()->create([
            'username' => 'stranger1',
            'display_name' => 'Newcomer Alice',
            'email' => 'stranger1@example.com',
            'password' => Hash::make('password'),
            'role' => 'stranger',
            'base_integrity' => 10.0,
            'available_integrity' => 10.0,
        ]);
        
        User::factory()->create([
            'username' => 'stranger2',
            'display_name' => 'Newcomer Bob',
            'email' => 'stranger2@example.com',
            'password' => Hash::make('password'),
            'role' => 'stranger',
            'base_integrity' => 5.0,
            'available_integrity' => 5.0,
        ]);
        
        // Sojourners (medium reputation, some vouches)
        User::factory()->create([
            'username' => 'sojourner1',
            'display_name' => 'Traveler Carol',
            'email' => 'sojourner1@example.com',
            'password' => Hash::make('password'),
            'role' => 'sojourner',
            'base_integrity' => 35.0,
            'available_integrity' => 30.0,
        ]);
        
        User::factory()->create([
            'username' => 'sojourner2',
            'display_name' => 'Traveler David',
            'email' => 'sojourner2@example.com',
            'password' => Hash::make('password'),
            'role' => 'sojourner',
            'base_integrity' => 45.0,
            'available_integrity' => 40.0,
        ]);
        
        // Denizens (good reputation, established)
        User::factory()->create([
            'username' => 'denizen1',
            'display_name' => 'Resident Eve',
            'email' => 'denizen1@example.com',
            'password' => Hash::make('password'),
            'role' => 'denizen',
            'base_integrity' => 65.0,
            'available_integrity' => 55.0,
        ]);
        
        User::factory()->create([
            'username' => 'denizen2',
            'display_name' => 'Resident Frank',
            'email' => 'denizen2@example.com',
            'password' => Hash::make('password'),
            'role' => 'denizen',
            'base_integrity' => 75.0,
            'available_integrity' => 65.0,
        ]);
        
        User::factory()->create([
            'username' => 'denizen3',
            'display_name' => 'Resident Grace',
            'email' => 'denizen3@example.com',
            'password' => Hash::make('password'),
            'role' => 'denizen',
            'base_integrity' => 80.0,
            'available_integrity' => 70.0,
        ]);
        
        // Stewards (high reputation, trusted)
        User::factory()->create([
            'username' => 'steward1',
            'display_name' => 'Steward Henry',
            'email' => 'steward1@example.com',
            'password' => Hash::make('password'),
            'role' => 'steward',
            'base_integrity' => 85.0,
            'available_integrity' => 75.0,
        ]);
        
        User::factory()->create([
            'username' => 'steward2',
            'display_name' => 'Steward Iris',
            'email' => 'steward2@example.com',
            'password' => Hash::make('password'),
            'role' => 'steward',
            'base_integrity' => 90.0,
            'available_integrity' => 80.0,
        ]);
        
        // Elders (very high reputation, highly trusted)
        User::factory()->create([
            'username' => 'elder1',
            'display_name' => 'Elder Jack',
            'email' => 'elder1@example.com',
            'password' => Hash::make('password'),
            'role' => 'elder',
            'base_integrity' => 95.0,
            'available_integrity' => 85.0,
        ]);
        
        User::factory()->create([
            'username' => 'elder2',
            'display_name' => 'Elder Kate',
            'email' => 'elder2@example.com',
            'password' => Hash::make('password'),
            'role' => 'elder',
            'base_integrity' => 98.0,
            'available_integrity' => 88.0,
        ]);
        
        // High Reeve (highest reputation, leadership)
        User::factory()->create([
            'username' => 'high_reeve',
            'display_name' => 'High Reeve Leo',
            'email' => 'high_reeve@example.com',
            'password' => Hash::make('password'),
            'role' => 'high_reeve',
            'base_integrity' => 99.5,
            'available_integrity' => 90.0,
        ]);
        
        $this->command->info('Created user population with different roles:');
        $this->command->info('3 Strangers, 2 Sojourners, 3 Denizens, 2 Stewards, 2 Elders, 1 High Reeve');
        $this->command->info('');
        $this->command->info('Login credentials (password: "password"):');
        $this->command->info('Stranger: stranger1@example.com');
        $this->command->info('Sojourner: sojourner1@example.com');
        $this->command->info('Denizen: denizen1@example.com');
        $this->command->info('Steward: steward1@example.com');
        $this->command->info('Elder: elder1@example.com');
        $this->command->info('High Reeve: high_reeve@example.com');
    }
}
