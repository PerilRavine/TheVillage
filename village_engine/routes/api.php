<?php

use App\Http\Controllers\P2PController;
use App\Http\Controllers\DomicileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::get('/villages', function () {
    return response()->json([
        ['id' => 'village1', 'name' => 'Tech Village', 'reputation' => 85.0],
        ['id' => 'village2', 'name' => 'Art Village', 'reputation' => 72.5],
        ['id' => 'village3', 'name' => 'Science Village', 'reputation' => 91.2],
    ]);
});

Route::get('/user', function () {
    return response()->json([
        'user' => [
            'id' => 'demo',
            'username' => 'demo_user',
            'display_name' => 'Demo User',
            'email' => 'demo@village.local',
            'role' => 'stranger',
            'base_integrity' => 50.0,
            'available_integrity' => 50.0,
            'locked_integrity' => 0.0
        ],
        'permissions' => [
            'can_view_domiciles' => true,
            'can_create_domicile' => false,
            'can_manage_users' => false
        ]
    ]);
});

// Authentication routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/switch-role', [AuthController::class, 'switchRole']);
Route::get('/test-users', function () {
    return response()->json([
        'users' => [
            [
                'id' => 1,
                'username' => 'stranger_user',
                'display_name' => 'Stranger User',
                'email' => 'stranger@village.local',
                'role' => 'stranger',
                'base_integrity' => 50.0
            ],
            [
                'id' => 2,
                'username' => 'sojourner_user',
                'display_name' => 'Sojourner User',
                'email' => 'sojourner@village.local',
                'role' => 'sojourner',
                'base_integrity' => 65.0
            ],
            [
                'id' => 3,
                'username' => 'denizen_user',
                'display_name' => 'Denizen User',
                'email' => 'denizen@village.local',
                'role' => 'denizen',
                'base_integrity' => 80.0
            ]
        ]
    ]);
});
Route::post('/auth/login-as', [AuthController::class, 'loginAs']);

// Admin routes
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::post('/server', [AdminController::class, 'storeServer']);
    Route::post('/village', [AdminController::class, 'createVillage']);
    Route::get('/stats/villages', [AdminController::class, 'getVillageStats']);
    Route::get('/network/overview', [AdminController::class, 'getNetworkOverview']);
});

Route::middleware('auth')->group(function () {
    // P2P Discovery Routes
    Route::post('/p2p/discovery/request', [P2PController::class, 'requestPeerDiscovery']);
    Route::post('/p2p/discovery/respond', [P2PController::class, 'respondToPeerDiscovery']);
    Route::get('/p2p/peers', [P2PController::class, 'getAvailablePeers']);
    Route::get('/p2p/peer/{peerId}/status', [P2PController::class, 'getPeerStatus']);
    
    // Domicile Management Routes
    Route::get('/domiciles', [DomicileController::class, 'index']);
    Route::post('/domiciles', [DomicileController::class, 'store']);
    Route::get('/domiciles/{domicile}', [DomicileController::class, 'show']);
    Route::put('/domiciles/{domicile}', [DomicileController::class, 'update']);
    Route::delete('/domiciles/{domicile}', [DomicileController::class, 'destroy']);
    
    // Domicile Content Routes
    Route::post('/domiciles/{domicile}/content', [DomicileController::class, 'uploadContent']);
    Route::get('/domiciles/{domicile}/content', [DomicileController::class, 'getContent']);
    Route::get('/domiciles/{domicile}/content/{content}', [DomicileController::class, 'showContent']);
    Route::delete('/domiciles/{domicile}/content/{content}', [DomicileController::class, 'deleteContent']);
    Route::get('/domiciles/{domicile}/content/{content}/download', [DomicileController::class, 'downloadContent']);
    
    // Domicile Sharing Routes
    Route::post('/domiciles/{domicile}/share', [DomicileController::class, 'share']);
    Route::post('/domiciles/access-shared', [DomicileController::class, 'accessShared']);
    Route::get('/domiciles/{domicile}/shared-access', [DomicileController::class, 'getSharedAccess']);
    Route::delete('/domiciles/{domicile}/shared-access/{sharedAccess}', [DomicileController::class, 'revokeAccess']);
    
    // Domicile Analytics Routes
    Route::get('/domiciles/{domicile}/analytics', [DomicileController::class, 'getAnalytics']);
    Route::get('/domiciles/{domicile}/brand-tracking', [DomicileController::class, 'getBrandTracking']);
    Route::get('/domiciles/{domicile}/access-logs', [DomicileController::class, 'getAccessLogs']);
    Route::get('/domiciles/{domicile}/statistics', [DomicileController::class, 'getStatistics']);
});
