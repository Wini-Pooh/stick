<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;
use App\Models\TelegramUserActivity;

class TelegramBotController extends Controller
{
    private $botToken;
    private $botUrl;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN' ?? '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
        $this->botUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Webhook для обработки сообщений от Telegram
     */
    public function webhook(Request $request)
    {
        $update = $request->all();
        Log::info('Telegram webhook received', $update);

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Обработка входящих сообщений
     */
    private function handleMessage($message)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $user = $message['from'] ?? null;

        // Создаем или обновляем пользователя в базе данных
        $telegramUser = null;
        if ($user) {
            $fakeInitData = [
                'user' => json_encode($user),
                'auth_date' => time(),
            ];
            $telegramUser = TelegramUser::createOrUpdate($fakeInitData);
            
            // Логируем сообщение от пользователя
            if ($telegramUser) {
                TelegramUserActivity::log(
                    $telegramUser,
                    'bot_message',
                    'telegram.webhook',
                    [
                        'message_text' => $text,
                        'chat_id' => $chatId,
                        'message_id' => $message['message_id'] ?? null,
                    ]
                );
            }
        }

        switch ($text) {
            case '/start':
                $this->sendWelcomeMessage($chatId, $telegramUser);
                break;
                
            case '/miniapp':
                $this->sendMiniAppButton($chatId);
                break;

            case '/stats':
                $this->sendStats($chatId);
                break;
                
            default:
                $this->sendMiniAppButton($chatId);
                break;
        }
    }

    /**
     * Отправка приветственного сообщения
     */
    private function sendWelcomeMessage($chatId, $telegramUser = null)
    {
        $userName = $telegramUser ? $telegramUser->first_name : 'друг';
        $visitCount = $telegramUser ? $telegramUser->visits_count : 1;
        
        $message = "🚀 Добро пожаловать, {$userName}!\n\n";
        $message .= "Это ваш визит #{$visitCount} в наш Telegram Mini App.\n\n";
        $message .= "Нажмите кнопку ниже, чтобы открыть мини-приложение:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🚀 Открыть Mini App',
                        'web_app' => [
                            'url' => env('APP_URL') . '/miniapp'
                        ]
                    ]
                ],
                [
                    [
                        'text' => '📊 Статистика',
                        'callback_data' => 'stats'
                    ]
                ]
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }

    /**
     * Отправка кнопки для открытия Mini App
     */
    private function sendMiniAppButton($chatId)
    {
        $message = "🎯 Откройте наше мини-приложение для просмотра профиля и отладочной информации:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🚀 Открыть Mini App',
                        'web_app' => [
                            'url' => env('APP_URL') . '/miniapp'
                        ]
                    ]
                ],
                [
                    [
                        'text' => '📖 Помощь',
                        'callback_data' => 'help'
                    ],
                    [
                        'text' => '📊 Статистика',
                        'callback_data' => 'stats'
                    ]
                ]
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }

    /**
     * Отправка статистики
     */
    private function sendStats($chatId)
    {
        try {
            $totalUsers = TelegramUser::count();
            $activeToday = TelegramUser::whereDate('last_seen_at', today())->count();
            $newToday = TelegramUser::whereDate('first_seen_at', today())->count();
            $totalActivities = TelegramUserActivity::count();
            
            $message = "📊 <b>Статистика Mini App:</b>\n\n";
            $message .= "👥 Всего пользователей: {$totalUsers}\n";
            $message .= "🟢 Активных сегодня: {$activeToday}\n";
            $message .= "🆕 Новых сегодня: {$newToday}\n";
            $message .= "📈 Всего активностей: {$totalActivities}\n\n";
            $message .= "📅 Обновлено: " . now()->format('d.m.Y H:i');
            
            $this->sendMessage($chatId, $message);
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ Ошибка получения статистики: " . $e->getMessage());
        }
    }

    /**
     * Отправка сообщения
     */
    private function sendMessage($chatId, $text, $keyboard = null)
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        Http::post($this->botUrl . '/sendMessage', $params);
    }

    /**
     * Установка webhook
     */
    public function setWebhook()
    {
        $webhookUrl = env('APP_URL') . '/telegram/webhook';
        
        $response = Http::post($this->botUrl . '/setWebhook', [
            'url' => $webhookUrl
        ]);

        return response()->json([
            'success' => $response->successful(),
            'webhook_url' => $webhookUrl,
            'response' => $response->json()
        ]);
    }

    /**
     * Получение информации о webhook
     */
    public function getWebhookInfo()
    {
        $response = Http::get($this->botUrl . '/getWebhookInfo');
        
        return response()->json($response->json());
    }

    /**
     * Удаление webhook
     */
    public function deleteWebhook()
    {
        $response = Http::post($this->botUrl . '/deleteWebhook');
        
        return response()->json($response->json());
    }
}
