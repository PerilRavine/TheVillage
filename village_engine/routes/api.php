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
