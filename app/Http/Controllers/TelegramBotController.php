<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
                [
                    [
                        'text' => '📋 Правила',
                        'callback_data' => 'rules'
                    ],
                    [
                        'text' => '📊 Статистика',
                        'callback_data' => 'lotto_stats'
                    ]
                ]
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }elegramUser;
use App\Models\TelegramUserActivity;

class TelegramBotController extends Controller
{
    private $botToken;
    private $botUrl;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN' , '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
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

        // Обработка pre_checkout_query для платежей звёздами
        if (isset($update['pre_checkout_query'])) {
            $this->handlePreCheckoutQuery($update['pre_checkout_query']);
        }

        // Обработка успешных платежей
        if (isset($update['message']['successful_payment'])) {
            $this->handleSuccessfulPayment($update['message']);
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

            case '/terms':
                $this->sendTerms($chatId);
                break;

            case '/support':
                $this->sendSupport($chatId);
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
        
        $message = "⭐ Добро пожаловать в Звёздное Лото, {$userName}!\n\n";
        $message .= "🎰 Донатьте звёзды Telegram и участвуйте в ежедневных розыгрышах с шансом удвоить, утроить или получить в 10 раз больше звёзд!\n\n";
        $message .= "🎯 Особенности нашего лото:\n";
        $message .= "• Честные розыгрыши каждый день в 23:00 МСК\n";
        $message .= "• Разные игры с множителями x2, x3, x5, x10, x20\n";
        $message .= "• Мгновенное зачисление выигрышей\n";
        $message .= "• Прозрачная статистика розыгрышей\n\n";
        $message .= "Это ваш визит #{$visitCount}. Нажмите кнопку ниже, чтобы начать играть:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🎰 Играть в лото',
                        'web_app' => [
                            'url' => env('APP_URL') . '/miniapp'
                        ]
                    ]
                ],
                [
                    [
                        'text' => '📊 Статистика',
                        'callback_data' => 'lotto_stats'
                    ],
                    [
                        'text' => '🏆 Результаты',
                        'callback_data' => 'lotto_results'
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
        $message = "� Откройте Звёздное Лото и попробуйте свою удачу!\n\n";
        $message .= "⭐ Донатьте звёзды и выигрывайте в ежедневных розыгрышах!";

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🎰 Звёздное Лото',
                        'web_app' => [
                            'url' => env('APP_URL') . '/miniapp'
                        ]
                    ]
                ],
                [
                    [
                        'text' => '� Правила',
                        'callback_data' => 'rules'
                    ],
                    [
                        'text' => '📊 Статистика',
                        'callback_data' => 'lotto_stats'
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
        $webhookUrl = env('APP_URL') . '/api/telegram/webhook';
        
        $response = Http::post($this->botUrl . '/setWebhook', [
            'url' => $webhookUrl,
            'allowed_updates' => [
                'message',
                'edited_message', 
                'callback_query',
                'inline_query',
                'pre_checkout_query',     // Критично для Stars платежей!
                'successful_payment'      // Критично для Stars платежей!
            ]
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

    /**
     * Обработка pre-checkout запроса для платежей звёздами
     */
    private function handlePreCheckoutQuery($preCheckoutQuery)
    {
        $queryId = $preCheckoutQuery['id'];
        $payload = json_decode($preCheckoutQuery['invoice_payload'], true);

        Log::info('Pre-checkout query received', [
            'query_id' => $queryId,
            'payload' => $payload,
            'total_amount' => $preCheckoutQuery['total_amount'],
        ]);

        // Проверяем, что билет существует и ещё не оплачен
        if (isset($payload['ticket_id'])) {
            $ticket = \App\Models\LottoTicket::find($payload['ticket_id']);
            
            if (!$ticket || $ticket->status !== 'pending') {
                Http::post($this->botUrl . '/answerPreCheckoutQuery', [
                    'pre_checkout_query_id' => $queryId,
                    'ok' => false,
                    'error_message' => 'Билет недействителен или уже оплачен',
                ]);
                return;
            }
        }

        // Подтверждаем оплату
        Http::post($this->botUrl . '/answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $queryId,
            'ok' => true,
        ]);
    }

    /**
     * Обработка успешного платежа
     */
    private function handleSuccessfulPayment($message)
    {
        $payment = $message['successful_payment'];
        $payload = json_decode($payment['invoice_payload'], true);

        Log::info('Successful payment received', [
            'payload' => $payload,
            'payment' => $payment,
            'chat_id' => $message['chat']['id'],
        ]);

        if (isset($payload['ticket_id'])) {
            $ticket = \App\Models\LottoTicket::find($payload['ticket_id']);
            
            if (!$ticket) {
                Log::error('Ticket not found for successful payment', $payload);
                return;
            }

            // Обновляем билет
            $ticket->update([
                'status' => 'participating',
                'purchased_at' => now(),
                'payment_charge_id' => $payment['telegram_payment_charge_id'],
                'payment_data' => $payment,
            ]);

            // Создаём или обновляем розыгрыш на сегодня
            \App\Models\LottoDraw::getOrCreateTodayDraw($ticket->lotto_game_id);

            // Отправляем подтверждение пользователю
            $this->sendPaymentConfirmation($message['chat']['id'], $ticket);

            Log::info('Lotto ticket payment confirmed', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'user_id' => $ticket->telegram_user_id,
            ]);
        }
    }

    /**
     * Отправить подтверждение оплаты
     */
    private function sendPaymentConfirmation($chatId, $ticket)
    {
        $game = $ticket->lottoGame;
        
        $text = "🎟️ Билет успешно оплачен!\n\n";
        $text .= "📄 Номер билета: {$ticket->ticket_number}\n";
        $text .= "🎰 Игра: {$game->name}\n";
        $text .= "💰 Потенциальный выигрыш: {$game->getPotentialWinnings()} ⭐\n";
        $text .= "🎲 Шанс выигрыша: " . ($game->win_chance * 100) . "%\n\n";
        $text .= "⏰ Розыгрыш пройдёт сегодня в 23:00 МСК\n";
        $text .= "🍀 Удачи!";

        Http::post($this->botUrl . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    /**
     * Отправить правила и условия использования
     */
    private function sendTerms($chatId)
    {
        $text = "📋 ПРАВИЛА И УСЛОВИЯ ИСПОЛЬЗОВАНИЯ\n\n";
        $text .= "🎰 **Звёздное Лото** - развлекательная игра с элементами везения.\n\n";
        $text .= "📝 ОСНОВНЫЕ ПРАВИЛА:\n";
        $text .= "• Покупка билетов осуществляется за Telegram Stars\n";
        $text .= "• Розыгрыши проводятся ежедневно в 23:00 МСК\n";
        $text .= "• Победители определяются случайным образом\n";
        $text .= "• Выигрыш зачисляется автоматически в Telegram Stars\n\n";
        $text .= "⚠️ ОГРАНИЧЕНИЯ:\n";
        $text .= "• Игра предназначена для лиц старше 18 лет\n";
        $text .= "• Максимальная ответственность за споры лежит на игроке\n";
        $text .= "• Результаты розыгрышей окончательны и не подлежат пересмотру\n\n";
        $text .= "💳 ВОЗВРАТ СРЕДСТВ:\n";
        $text .= "• Возврат возможен только в случае технических ошибок\n";
        $text .= "• Запросы на возврат обрабатываются в течение 24 часов\n\n";
        $text .= "📞 Для поддержки используйте команду /support";

        Http::post($this->botUrl . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Отправить информацию о поддержке
     */
    private function sendSupport($chatId)
    {
        $text = "🆘 СЛУЖБА ПОДДЕРЖКИ\n\n";
        $text .= "Мы готовы помочь вам с любыми вопросами!\n\n";
        $text .= "📞 СПОСОБЫ СВЯЗИ:\n";
        $text .= "• Telegram: @YourSupportBot\n";
        $text .= "• Email: support@yourdomain.com\n";
        $text .= "• Время работы: 24/7\n\n";
        $text .= "❗ ВАЖНО:\n";
        $text .= "• Поддержка Telegram НЕ сможет помочь с покупками через нашего бота\n";
        $text .= "• Все вопросы по платежам решаются только через нашу поддержку\n";
        $text .= "• При обращении укажите номер билета или ID транзакции\n\n";
        $text .= "⏰ ВРЕМЯ ОТВЕТА:\n";
        $text .= "• Технические вопросы: до 2 часов\n";
        $text .= "• Вопросы по платежам: до 24 часов\n";
        $text .= "• Возврат средств: до 48 часов\n\n";
        $text .= "🔄 Для возврата к главному меню используйте /start";

        Http::post($this->botUrl . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
}
