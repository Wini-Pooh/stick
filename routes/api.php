<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LottoController;
use App\Http\Controllers\TelegramBotController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Telegram Bot webhook для API
Route::prefix('telegram')->group(function () {
    Route::post('/webhook', [TelegramBotController::class, 'webhook'])->name('api.telegram.webhook');
});

// Lotto API routes
Route::prefix('lotto')->group(function () {
    Route::get('/games', [LottoController::class, 'getGames']);
    Route::post('/buy-ticket', [LottoController::class, 'buyTicket']);
    Route::post('/user-tickets', [LottoController::class, 'getUserTickets']);
    Route::post('/user-stats', [LottoController::class, 'getUserStats']);
    Route::get('/draw-results', [LottoController::class, 'getDrawResults']);
    Route::post('/confirm-payment', [LottoController::class, 'confirmPayment']);
});
