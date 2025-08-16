<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\FakeTelegramAuthController;
use App\Http\Controllers\TelegramBotController;

/*
|--------------------------------------------------------------------------
| Web Routes
// Имитация входа через Telegram
Route::get('/register', [FakeTelegramAuthController::class, 'showRegisterForm'])->name('fake-tg.register');
Route::post('/register', [FakeTelegramAuthController::class, 'register'])->name('fake-tg.register.post');
Route::get('/logout', [FakeTelegramAuthController::class, 'logout'])->name('fake-tg.logout');
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Тестовая страница для проверки работы сервера
Route::get('/server-test', function () {
    return view('test-page');
});

// Тестовая страница для Mini App
Route::get('/test', function () {
    return view('test');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Telegram Mini App routes
Route::prefix('miniapp')->group(function () {
    // Главная страница Mini App (без middleware для первоначальной загрузки)
    Route::get('/', [App\Http\Controllers\MiniAppController::class, 'index'])->name('miniapp.index');
    
    // Debug страница для диагностики
    Route::get('/debug-page', function () {
        return view('debug-miniapp');
    })->name('miniapp.debug-page');
    
    // Test endpoint без проверки подписи
    Route::get('/test', [App\Http\Controllers\MiniAppController::class, 'testEndpoint'])->name('miniapp.test');
    Route::post('/test-post', [App\Http\Controllers\MiniAppController::class, 'testPostEndpoint'])->name('miniapp.test-post');
    
    // Временные endpoints без проверки подписи для отладки
    Route::post('/profile-debug', [App\Http\Controllers\MiniAppController::class, 'profileDebug'])->name('miniapp.profile-debug');
    Route::post('/debug-debug', [App\Http\Controllers\MiniAppController::class, 'debugInfoDebug'])->name('miniapp.debug-debug');
    Route::post('/save-score', [App\Http\Controllers\MiniAppController::class, 'saveGameScore'])->name('miniapp.save-score');
    Route::post('/game-stats', [App\Http\Controllers\MiniAppController::class, 'getGameStats'])->name('miniapp.game-stats');
    
    // Статистика (открытый endpoint для демонстрации)
    Route::get('/stats', [App\Http\Controllers\MiniAppController::class, 'userStats'])->name('miniapp.stats');
    
    // Защищенные API endpoints с проверкой подписи Telegram
    Route::middleware([App\Http\Middleware\VerifyTelegramInitData::class])->group(function () {
        Route::post('/profile', [App\Http\Controllers\MiniAppController::class, 'profile'])->name('miniapp.profile');
        Route::post('/debug', [App\Http\Controllers\MiniAppController::class, 'debugInfo'])->name('miniapp.debug');
    });
    
    // Получение фотографии пользователя
    Route::get('/user-photo/{userId}', [App\Http\Controllers\MiniAppController::class, 'getUserPhoto'])->name('miniapp.user-photo');
});

// Telegram Bot routes - API endpoints
Route::prefix('api/telegram')->group(function () {
    Route::post('/webhook', [App\Http\Controllers\TelegramBotController::class, 'webhook'])->name('api.telegram.webhook');
});

// Telegram Bot routes - Admin endpoints  
Route::prefix('telegram')->group(function () {
    Route::get('/set-webhook', [App\Http\Controllers\TelegramBotController::class, 'setWebhook'])->name('telegram.set-webhook');
    Route::get('/set-webhook-stars', [App\Http\Controllers\TelegramBotController::class, 'setWebhookWithStars'])->name('telegram.set-webhook-stars');
    Route::get('/webhook-info', [App\Http\Controllers\TelegramBotController::class, 'getWebhookInfo'])->name('telegram.webhook-info');
    Route::get('/delete-webhook', [App\Http\Controllers\TelegramBotController::class, 'deleteWebhook'])->name('telegram.delete-webhook');
});

// Страница управления ботом
Route::get('/bot-admin', function () {
    return view('telegram-bot-admin');
})->name('bot.admin');
