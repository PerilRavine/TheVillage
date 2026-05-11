<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * AuthController - Handle user authentication and role switching
 * 
 * Provides endpoints for login, logout, and role-based viewing
 */
class AuthController extends Controller
{
    /**
     * Login user and return token
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            // Create session token
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'base_integrity' => $user->base_integrity,
                    'available_integrity' => $user->available_integrity,
                    'locked_integrity' => $user->locked_integrity,
                ],
                'token' => $token,
                'permissions' => $this->getUserPermissions($user),
            ]);
        }

        return response()->json([
            'error' => 'Invalid credentials',
        ], 401);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        
        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'role' => $user->role,
                'base_integrity' => $user->base_integrity,
                'available_integrity' => $user->available_integrity,
                'locked_integrity' => $user->locked_integrity,
            ],
            'permissions' => $this->getUserPermissions($user),
        ]);
    }

    /**
     * Switch to different user role for testing
     */
    public function switchRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:stranger,sojourner,denizen,steward,elder,high_reeve',
        ]);

        $user = Auth::user();
        $targetRole = $request->role;

        // Find a user with the target role
        $targetUser = User::where('role', $targetRole)->first();
        
        if (!$targetUser) {
            return response()->json([
                'error' => 'No user found with role: ' . $targetRole,
            ], 404);
        }

        // Log out current user and log in as target user
        Auth::logout();
        Auth::login($targetUser);

        return response()->json([
            'message' => "Switched to {$targetRole} role",
            'user' => [
                'id' => $targetUser->id,
                'username' => $targetUser->username,
                'display_name' => $targetUser->display_name,
                'email' => $targetUser->email,
                'role' => $targetUser->role,
                'base_integrity' => $targetUser->base_integrity,
                'available_integrity' => $targetUser->available_integrity,
                'locked_integrity' => $targetUser->locked_integrity,
            ],
            'permissions' => $this->getUserPermissions($targetUser),
        ]);
    }

    /**
     * Get all available test users
     */
    public function getTestUsers(): JsonResponse
    {
        $users = User::select('id', 'username', 'display_name', 'email', 'role', 'base_integrity')
            ->orderBy('base_integrity', 'asc')
            ->get();

        return response()->json([
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'base_integrity' => $user->base_integrity,
                    'role_display' => $this->getRoleDisplay($user->role),
                    'can_login' => true,
                ];
            }),
        ]);
    }

    /**
     * Login as specific user
     */
    public function loginAs(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::find($request->user_id);
        
        if (!$user) {
            return response()->json([
                'error' => 'User not found',
            ], 404);
        }

        // Log out current user and log in as target user
        Auth::logout();
        Auth::login($user);

        return response()->json([
            'message' => "Logged in as {$user->display_name}",
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'role' => $user->role,
                'base_integrity' => $user->base_integrity,
                'available_integrity' => $user->available_integrity,
                'locked_integrity' => $user->locked_integrity,
            ],
            'permissions' => $this->getUserPermissions($user),
        ]);
    }

    /**
     * Get user permissions based on role
     */
    private function getUserPermissions(User $user): array
    {
        $permissions = [
            'can_view_village' => true,
            'can_view_domiciles' => true,
            'can_create_domicile' => false,
            'can_upload_content' => false,
            'can_share_content' => false,
            'can_vouch_for_users' => false,
            'can_moderate' => false,
            'can_administer' => false,
        ];

        switch ($user->role) {
            case 'stranger':
                $permissions['can_view_village'] = true;
                $permissions['can_view_domiciles'] = false;
                break;

            case 'sojourner':
                $permissions['can_view_village'] = true;
                $permissions['can_view_domiciles'] = true;
                $permissions['can_create_domicile'] = false;
                break;

            case 'denizen':
                $permissions['can_view_village'] = true;
                $permissions['can_view_domiciles'] = true;
                $permissions['can_create_domicile'] = true;
                $permissions['can_upload_content'] = true;
                $permissions['can_share_content'] = true;
                break;

            case 'steward':
                $permissions['can_view_village'] = true;
                $permissions['can_view_domiciles'] = true;
                $permissions['can_create_domicile'] = true;
                $permissions['can_upload_content'] = true;
                $permissions['can_share_content'] = true;
                $permissions['can_vouch_for_users'] = true;
                break;

            case 'elder':
                $permissions['can_view_village'] = true;
                $permissions['can_view_domiciles'] = true;
                $permissions['can_create_domicile'] = true;
                $permissions['can_upload_content'] = true;
                $permissions['can_share_content'] = true;
                $permissions['can_vouch_for_users'] = true;
                $permissions['can_moderate'] = true;
                break;

            case 'high_reeve':
                $permissions['can_view_village'] = true;
                $permissions['can_view_domiciles'] = true;
                $permissions['can_create_domicile'] = true;
                $permissions['can_upload_content'] = true;
                $permissions['can_share_content'] = true;
                $permissions['can_vouch_for_users'] = true;
                $permissions['can_moderate'] = true;
                $permissions['can_administer'] = true;
                break;
        }

        return $permissions;
    }

    /**
     * Get role display name
     */
    private function getRoleDisplay(string $role): string
    {
        return match($role) {
            'stranger' => 'Stranger',
            'sojourner' => 'Sojourner',
            'denizen' => 'Denizen',
            'steward' => 'Steward',
            'elder' => 'Elder',
            'high_reeve' => 'High Reeve',
            default => 'Unknown',
        };
    }
}
