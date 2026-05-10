<?php

use App\Http\Controllers\P2PController;
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
});
