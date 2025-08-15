<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\TelegramUser;
use App\Models\TelegramUserActivity;

class MiniAppController extends Controller
{
    /**
     * Главная страница Mini App
     */
    public function index(Request $request)
    {
        // Получаем initData из параметров запроса для передачи во view
        $initData = $request->query('initData', '');
        
        // Логируем открытие мини-приложения (без проверки подписи)
        if ($initData) {
            try {
                parse_str($initData, $data);
                $telegramUser = TelegramUser::createOrUpdate($data);
                if ($telegramUser) {
                    TelegramUserActivity::log(
                        $telegramUser,
                        'miniapp_open',
                        'miniapp.index',
                        ['referrer' => $request->header('Referer')],
                        $request
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log miniapp open: ' . $e->getMessage());
            }
        }
        
        return view('miniapp.index', compact('initData'));
    }

    /**
     * Получение профиля пользователя из initData
     */
    public function profile(Request $request)
    {
        $telegramUser = $request->get('telegram_user');
        $telegramData = $request->get('telegram_user_data', []);
        
        if (!$telegramUser) {
            return response()->json([
                'success' => false,
                'error' => 'User not found in database'
            ], 404);
        }

        $userFromTelegram = isset($telegramData['user']) ? json_decode($telegramData['user'], true) : null;
        
        return response()->json([
            'success' => true,
            'user' => $userFromTelegram,
            'database_user' => [
                'id' => $telegramUser->id,
                'telegram_id' => $telegramUser->telegram_id,
                'display_name' => $telegramUser->display_name,
                'full_name' => $telegramUser->full_name,
                'username' => $telegramUser->username,
                'language_code' => $telegramUser->language_code,
                'is_premium' => $telegramUser->is_premium,
                'first_seen_at' => $telegramUser->first_seen_at->toISOString(),
                'last_seen_at' => $telegramUser->last_seen_at->toISOString(),
                'visits_count' => $telegramUser->visits_count,
                'is_online' => $telegramUser->is_online,
                'last_seen_human' => $telegramUser->last_seen_human,
            ],
            'auth_date' => $telegramData['auth_date'] ?? null,
            'query_id' => $telegramData['query_id'] ?? null,
        ]);
    }

    /**
     * Вывод debug-информации
     */
    public function debugInfo(Request $request)
    {
        $telegramUser = $request->get('telegram_user');
        $telegramData = $request->get('telegram_user_data', []);
        $initData = $request->input('initData') ?? $request->header('X-Telegram-Init-Data');
        
        // Получаем последние активности пользователя
        $recentActivities = [];
        if ($telegramUser) {
            $recentActivities = TelegramUserActivity::forUser($telegramUser->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($activity) {
                    return [
                        'action' => $activity->action,
                        'endpoint' => $activity->endpoint,
                        'created_at' => $activity->created_at->toISOString(),
                        'ip_address' => $activity->ip_address,
                    ];
                });
        }
        
        // Статистика базы данных
        $dbStats = [
            'total_users' => TelegramUser::count(),
            'active_users_24h' => TelegramUser::active()->count(),
            'recent_users_7d' => TelegramUser::recent()->count(),
            'total_activities' => TelegramUserActivity::count(),
            'activities_24h' => TelegramUserActivity::recent(24)->count(),
        ];
        
        return response()->json([
            'success' => true,
            'debug_info' => [
                'telegram_data' => $telegramData,
                'database_user' => $telegramUser ? [
                    'id' => $telegramUser->id,
                    'telegram_id' => $telegramUser->telegram_id,
                    'visits_count' => $telegramUser->visits_count,
                    'first_seen_at' => $telegramUser->first_seen_at->toISOString(),
                    'last_seen_at' => $telegramUser->last_seen_at->toISOString(),
                ] : null,
                'recent_activities' => $recentActivities,
                'database_stats' => $dbStats,
                'request_info' => [
                    'user_agent' => $request->header('User-Agent'),
                    'ip_address' => $request->ip(),
                    'environment' => app()->environment(),
                    'request_time' => now()->toISOString(),
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'query_params' => $request->query(),
                ],
                'raw_init_data' => $initData,
                'headers' => $request->headers->all(),
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'database_connection' => config('database.default'),
                ],
            ]
        ]);
    }

    /**
     * API endpoint без проверки подписи для тестирования
     */
    public function testEndpoint(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Test endpoint working',
            'timestamp' => now()->toISOString(),
            'query_params' => $request->query(),
            'headers' => $request->headers->all(),
            'database_status' => [
                'total_telegram_users' => TelegramUser::count(),
                'total_activities' => TelegramUserActivity::count(),
            ],
        ]);
    }

    /**
     * POST endpoint для тестирования без проверки подписи
     */
    public function testPostEndpoint(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Test POST endpoint working',
            'timestamp' => now()->toISOString(),
            'request_data' => [
                'method' => $request->method(),
                'headers' => $request->headers->all(),
                'body' => $request->all(),
                'initData_present' => !empty($request->input('initData')),
                'initData_length' => strlen($request->input('initData', '')),
            ],
        ]);
    }

    /**
     * Профиль пользователя без проверки подписи (для отладки)
     */
    public function profileDebug(Request $request)
    {
        $initData = $request->input('initData') ?? $request->header('X-Telegram-Init-Data');
        
        if (!$initData) {
            return response()->json([
                'error' => 'No initData provided',
                'debug' => [
                    'headers' => $request->headers->all(),
                    'body' => $request->all(),
                ]
            ], 400);
        }

        // Парсим данные без проверки подписи
        parse_str($initData, $data);
        $userFromTelegram = isset($data['user']) ? json_decode($data['user'], true) : null;
        
        return response()->json([
            'success' => true,
            'message' => 'Profile debug endpoint (no signature validation)',
            'user' => $userFromTelegram,
            'raw_data' => $data,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Debug информация без проверки подписи (для отладки)
     */
    public function debugInfoDebug(Request $request)
    {
        $initData = $request->input('initData') ?? $request->header('X-Telegram-Init-Data');
        
        return response()->json([
            'success' => true,
            'message' => 'Debug endpoint (no signature validation)',
            'debug_info' => [
                'initData_present' => !empty($initData),
                'initData_length' => strlen($initData ?: ''),
                'initData_sample' => substr($initData ?: '', 0, 100) . '...',
                'parsed_data' => $initData ? $this->parseInitDataSafely($initData) : null,
                'request_headers' => $request->headers->all(),
                'request_body' => $request->all(),
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'database_connection' => config('database.default'),
                    'app_env' => app()->environment(),
                ],
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Безопасное парсинг initData
     */
    private function parseInitDataSafely($initData)
    {
        try {
            parse_str($initData, $data);
            return $data;
        } catch (\Exception $e) {
            return ['error' => 'Failed to parse initData: ' . $e->getMessage()];
        }
    }

    /**
     * Получить статистику пользователей (для админки)
     */
    public function userStats(Request $request)
    {
        $stats = [
            'total_users' => TelegramUser::count(),
            'new_users_today' => TelegramUser::whereDate('first_seen_at', today())->count(),
            'active_users_today' => TelegramUser::whereDate('last_seen_at', today())->count(),
            'premium_users' => TelegramUser::where('is_premium', true)->count(),
            'top_languages' => TelegramUser::selectRaw('language_code, COUNT(*) as count')
                ->groupBy('language_code')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'recent_activities' => TelegramUserActivity::recent(24)
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Сохранение результата игры "Змейка"
     */
    public function saveGameScore(Request $request)
    {
        try {
            $initData = $request->input('initData') ?? $request->header('X-Telegram-Init-Data');
            $score = $request->input('score', 0);
            $highScore = $request->input('high_score', 0);
            $totalScore = $request->input('total_score', 0);
            $gamesPlayed = $request->input('games_played', 0);
            
            if ($initData) {
                $telegramData = $this->parseInitDataSafely($initData);
                $telegramUser = TelegramUser::createOrUpdate($telegramData);
                
                if ($telegramUser) {
                    // Логируем результат игры
                    TelegramUserActivity::log(
                        $telegramUser,
                        'snake_game_score',
                        'miniapp.save-score',
                        [
                            'score' => $score,
                            'high_score' => $highScore,
                            'total_score' => $totalScore,
                            'games_played' => $gamesPlayed,
                            'timestamp' => now(),
                        ],
                        $request
                    );
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Результат сохранён!',
                        'user' => $telegramUser->display_name,
                        'score' => $score,
                        'high_score' => $highScore,
                        'total_score' => $totalScore,
                        'games_played' => $gamesPlayed,
                    ]);
                }
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Не удалось сохранить результат',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error saving game score: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сохранения результата',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение статистики игрока по змейке
     */
    public function getGameStats(Request $request)
    {
        try {
            $initData = $request->input('initData') ?? $request->header('X-Telegram-Init-Data');
            
            if ($initData) {
                $telegramData = $this->parseInitDataSafely($initData);
                $telegramUser = TelegramUser::createOrUpdate($telegramData);
                
                if ($telegramUser) {
                    // Получаем статистику игры из активности пользователя
                    $gameActivities = TelegramUserActivity::where('telegram_user_id', $telegramUser->id)
                        ->where('action', 'snake_game_score')
                        ->orderBy('created_at', 'desc')
                        ->get();
                    
                    $stats = [
                        'total_games' => $gameActivities->count(),
                        'best_score' => $gameActivities->max('data->score') ?? 0,
                        'total_score' => $gameActivities->sum('data->score') ?? 0,
                        'recent_scores' => $gameActivities->take(10)->pluck('data.score'),
                        'last_played' => $gameActivities->first()->created_at ?? null,
                    ];
                    
                    return response()->json([
                        'success' => true,
                        'user' => [
                            'name' => $telegramUser->display_name,
                            'avatar' => $telegramUser->first_name ? $telegramUser->first_name[0] : '?',
                        ],
                        'stats' => $stats,
                    ]);
                }
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не найден',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting game stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения статистики',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение фотографии пользователя через Telegram Bot API
     */
    public function getUserPhoto(Request $request, $userId)
    {
        try {
            $botToken = env('TELEGRAM_BOT_TOKEN');
            
            Log::info('Getting user photo', [
                'user_id' => $userId,
                'bot_token_set' => !empty($botToken)
            ]);
            
            if (!$botToken) {
                Log::error('Bot token not configured');
                return response()->json([
                    'success' => false,
                    'error' => 'Bot token not configured'
                ], 500);
            }
            
            // Получаем фотографии профиля пользователя
            $photosUrl = "https://api.telegram.org/bot{$botToken}/getUserProfilePhotos";
            $photosParams = [
                'user_id' => $userId,
                'limit' => 1,
                'offset' => 0
            ];
            
            Log::info('Requesting user photos', [
                'url' => $photosUrl,
                'params' => $photosParams
            ]);
            
            $response = Http::timeout(30)->get($photosUrl, $photosParams);
            
            Log::info('Photos API response', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            
            if (!$response->successful()) {
                Log::error('Failed to get user photos', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to get user photos',
                    'status' => $response->status(),
                    'telegram_error' => $response->json()
                ], 400);
            }
            
            $data = $response->json();
            
            if (!$data['ok']) {
                Log::error('Telegram API returned not ok', [
                    'response' => $data
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Telegram API error',
                    'telegram_error' => $data
                ], 400);
            }
            
            if (empty($data['result']['photos']) || empty($data['result']['photos'][0])) {
                Log::info('No profile photos found for user', ['user_id' => $userId]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'No profile photo found'
                ], 404);
            }
            
            // Берем фото наибольшего размера
            $photoSizes = $data['result']['photos'][0];
            $largestPhoto = end($photoSizes); // Последний элемент - самый большой
            $fileId = $largestPhoto['file_id'];
            
            Log::info('Found photo', [
                'file_id' => $fileId,
                'sizes_count' => count($photoSizes),
                'largest_size' => $largestPhoto
            ]);
            
            // Получаем информацию о файле
            $fileUrl = "https://api.telegram.org/bot{$botToken}/getFile";
            $fileParams = ['file_id' => $fileId];
            
            Log::info('Getting file info', [
                'url' => $fileUrl,
                'params' => $fileParams
            ]);
            
            $fileResponse = Http::timeout(30)->get($fileUrl, $fileParams);
            
            Log::info('File API response', [
                'status' => $fileResponse->status(),
                'response' => $fileResponse->json()
            ]);
            
            if (!$fileResponse->successful()) {
                Log::error('Failed to get file info', [
                    'status' => $fileResponse->status(),
                    'response' => $fileResponse->body()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to get file info',
                    'status' => $fileResponse->status()
                ], 400);
            }
            
            $fileData = $fileResponse->json();
            
            if (!$fileData['ok']) {
                Log::error('File API returned not ok', [
                    'response' => $fileData
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'File API error',
                    'telegram_error' => $fileData
                ], 400);
            }
            
            if (!isset($fileData['result']['file_path'])) {
                Log::error('File path not found in response', [
                    'response' => $fileData
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'File path not found'
                ], 400);
            }
            
            $filePath = $fileData['result']['file_path'];
            $photoUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
            
            Log::info('Photo URL generated', [
                'photo_url' => $photoUrl,
                'file_path' => $filePath
            ]);
            
            return response()->json([
                'success' => true,
                'photo_url' => $photoUrl,
                'file_id' => $fileId,
                'file_path' => $filePath,
                'debug' => [
                    'photos_count' => count($data['result']['photos']),
                    'sizes_count' => count($photoSizes),
                    'largest_photo' => $largestPhoto
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Exception in getUserPhoto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], 500);
        }
    }
}
