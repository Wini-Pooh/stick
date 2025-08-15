<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
}
