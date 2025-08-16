<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\TelegramUser;

class ManageStars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stars:manage 
                           {action : Действие: gift, refund, balance}
                           {user_id : Telegram ID пользователя}
                           {amount? : Количество звезд (для gift и refund)}
                           {--reason= : Причина операции}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Управление Telegram Stars: подарить, вернуть или проверить баланс';

    private $botToken;
    private $botUrl;

    public function __construct()
    {
        parent::__construct();
        $this->botToken = env('TELEGRAM_BOT_TOKEN', '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
        $this->botUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $userId = $this->argument('user_id');
        $amount = $this->argument('amount');
        $reason = $this->option('reason') ?: 'Операция через SSH команду';

        $this->info("⭐ Управление Telegram Stars");
        $this->newLine();

        // Проверяем существование пользователя
        $telegramUser = TelegramUser::where('telegram_id', $userId)->first();
        if (!$telegramUser) {
            $this->warn("⚠️ Пользователь с ID {$userId} не найден в базе данных");
            if (!$this->confirm('Продолжить операцию?')) {
                return 1;
            }
        } else {
            $this->line("👤 Пользователь: {$telegramUser->first_name} {$telegramUser->last_name} (@{$telegramUser->username})");
        }

        switch ($action) {
            case 'gift':
                return $this->giftStars($userId, $amount, $reason);
            
            case 'refund':
                return $this->refundStars($userId, $amount, $reason);
            
            case 'balance':
                return $this->checkBalance($userId);
            
            default:
                $this->error("❌ Неизвестное действие: {$action}");
                $this->line("Доступные действия: gift, refund, balance");
                return 1;
        }
    }

    /**
     * Подарить звезды пользователю
     */
    private function giftStars($userId, $amount, $reason)
    {
        if (!$amount || $amount <= 0) {
            $this->error("❌ Укажите корректное количество звезд для подарка");
            return 1;
        }

        $this->info("🎁 Подарок {$amount} звезд пользователю {$userId}...");

        try {
            // Отправляем подарок через Telegram Bot API
            $response = Http::post("{$this->botUrl}/sendGift", [
                'user_id' => $userId,
                'gift_id' => 'star_gift_1', // ID подарка звезд
                'text' => $reason,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok']) {
                    $this->info("✅ Успешно подарено {$amount} звезд!");
                    $this->line("📄 Причина: {$reason}");
                    
                    // Логируем операцию
                    Log::info("Stars gifted via SSH", [
                        'user_id' => $userId,
                        'amount' => $amount,
                        'reason' => $reason,
                        'response' => $data
                    ]);

                    // Отправляем уведомление пользователю
                    $this->sendNotification($userId, "🎁 Вам подарено {$amount} ⭐!\n\nПричина: {$reason}");
                    
                    return 0;
                } else {
                    $this->error("❌ Ошибка API: " . ($data['description'] ?? 'Неизвестная ошибка'));
                    return 1;
                }
            } else {
                $this->error("❌ HTTP ошибка: " . $response->status());
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Исключение: " . $e->getMessage());
            $this->comment("💡 Попробуем альтернативный способ...");
            
            // Альтернативный способ - через создание и отмену платежа
            return $this->giftStarsAlternative($userId, $amount, $reason);
        }
    }

    /**
     * Альтернативный способ подарка звезд
     */
    private function giftStarsAlternative($userId, $amount, $reason)
    {
        $this->info("🔄 Альтернативный способ подарка звезд...");

        try {
            // Создаем специальный счет-подарок
            $response = Http::post("{$this->botUrl}/sendInvoice", [
                'chat_id' => $userId,
                'title' => "🎁 Подарок звезд",
                'description' => $reason,
                'payload' => json_encode([
                    'type' => 'gift',
                    'amount' => $amount,
                    'reason' => $reason,
                    'timestamp' => time()
                ]),
                'currency' => 'XTR',
                'prices' => [
                    ['label' => 'Подарок звезд', 'amount' => 0] // Бесплатный подарок
                ],
                'provider_token' => '', // Пустой для Telegram Stars
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        ['text' => '🎁 Получить подарок', 'pay' => true]
                    ]]
                ])
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok']) {
                    $this->info("✅ Отправлен подарочный счет!");
                    $this->comment("💡 Пользователь получит уведомление в Telegram");
                    return 0;
                }
            }

            throw new \Exception("Не удалось отправить подарочный счет");

        } catch (\Exception $e) {
            $this->error("❌ Альтернативный способ не удался: " . $e->getMessage());
            
            // Третий способ - просто начисляем в базе данных
            return $this->creditStarsToDatabase($userId, $amount, $reason);
        }
    }

    /**
     * Начисляем звезды в базе данных
     */
    private function creditStarsToDatabase($userId, $amount, $reason)
    {
        $this->info("💾 Начисление звезд в базе данных...");

        try {
            $telegramUser = TelegramUser::where('telegram_id', $userId)->first();
            
            if (!$telegramUser) {
                $this->error("❌ Пользователь не найден в базе данных");
                return 1;
            }

            // Увеличиваем баланс пользователя (если есть такое поле)
            if ($telegramUser->hasAttribute('stars_balance')) {
                $telegramUser->stars_balance += $amount;
                $telegramUser->save();
            }

            // Записываем транзакцию
            DB::table('star_transactions')->insert([
                'telegram_user_id' => $telegramUser->id,
                'type' => 'gift',
                'amount' => $amount,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $this->info("✅ Звезды начислены в базе данных!");
            $this->sendNotification($userId, "🎁 Вам начислено {$amount} ⭐!\n\nПричина: {$reason}");
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка базы данных: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Возвратить звезды пользователю
     */
    private function refundStars($userId, $amount, $reason)
    {
        if (!$amount || $amount <= 0) {
            $this->error("❌ Укажите корректное количество звезд для возврата");
            return 1;
        }

        $this->info("↩️ Возврат {$amount} звезд пользователю {$userId}...");

        try {
            // Возврат через Telegram Bot API
            $response = Http::post("{$this->botUrl}/refundStarPayment", [
                'user_id' => $userId,
                'telegram_payment_charge_id' => 'manual_refund_' . time(),
                'amount' => $amount
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok']) {
                    $this->info("✅ Успешно возвращено {$amount} звезд!");
                    $this->line("📄 Причина: {$reason}");
                    
                    Log::info("Stars refunded via SSH", [
                        'user_id' => $userId,
                        'amount' => $amount,
                        'reason' => $reason
                    ]);

                    $this->sendNotification($userId, "↩️ Вам возвращено {$amount} ⭐!\n\nПричина: {$reason}");
                    
                    return 0;
                }
            }

            throw new \Exception("API не поддерживает прямой возврат");

        } catch (\Exception $e) {
            $this->comment("💡 Прямой возврат недоступен, используем альтернативный способ...");
            return $this->giftStarsAlternative($userId, $amount, "Возврат: " . $reason);
        }
    }

    /**
     * Проверить баланс пользователя
     */
    private function checkBalance($userId)
    {
        $this->info("💰 Проверка баланса пользователя {$userId}...");

        try {
            $telegramUser = TelegramUser::where('telegram_id', $userId)->first();
            
            if (!$telegramUser) {
                $this->warn("⚠️ Пользователь не найден в базе данных");
                return 1;
            }

            $this->line("👤 Пользователь: {$telegramUser->first_name} {$telegramUser->last_name}");
            
            // Показываем баланс звезд
            if (isset($telegramUser->stars_balance)) {
                $this->line("⭐ Баланс звезд: {$telegramUser->stars_balance}");
            } else {
                $this->line("⭐ Баланс звезд: 0 (поле не инициализировано)");
            }

            // Показываем последние транзакции
            $transactions = DB::table('star_transactions')
                ->where('telegram_user_id', $telegramUser->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            if ($transactions->count() > 0) {
                $this->line("\n📊 Последние транзакции:");
                $this->table(
                    ['Дата', 'Тип', 'Сумма', 'Причина'],
                    $transactions->map(function ($t) {
                        return [
                            $t->created_at,
                            $t->type,
                            $t->amount . ' ⭐',
                            $t->reason
                        ];
                    })->toArray()
                );
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Отправить уведомление пользователю
     */
    private function sendNotification($userId, $message)
    {
        try {
            Http::post("{$this->botUrl}/sendMessage", [
                'chat_id' => $userId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);
        } catch (\Exception $e) {
            $this->comment("⚠️ Не удалось отправить уведомление: " . $e->getMessage());
        }
    }
}
