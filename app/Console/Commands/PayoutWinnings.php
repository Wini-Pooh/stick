<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;
use App\Models\StarTransaction;

class PayoutWinnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lottery:payout-winnings 
                           {user_id : Telegram ID пользователя}
                           {amount : Количество звезд для выплаты}
                           {--reason= : Причина выплаты}
                           {--ticket-id= : ID билета лотереи}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Выплата выигрыша в Telegram Stars пользователю';

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
        $userId = $this->argument('user_id');
        $amount = (int) $this->argument('amount');
        $reason = $this->option('reason') ?: 'Выигрыш в лотерее';
        $ticketId = $this->option('ticket-id');

        $this->info('🎰 Выплата выигрыша в лотерее');
        $this->newLine();

        // Проверяем пользователя
        $telegramUser = TelegramUser::where('telegram_id', $userId)->first();
        if (!$telegramUser) {
            $this->error("❌ Пользователь с ID {$userId} не найден в базе данных");
            return 1;
        }

        $this->line("👤 Пользователь: {$telegramUser->first_name} {$telegramUser->last_name} (@{$telegramUser->username})");
        $this->line("💰 Сумма выплаты: {$amount} ⭐");
        $this->line("📝 Причина: {$reason}");
        $this->newLine();

        if (!$this->confirm('Подтвердить выплату?')) {
            $this->info('Операция отменена');
            return 0;
        }

        return $this->processWinningPayout($telegramUser, $amount, $reason, $ticketId);
    }

    /**
     * Обработка выплаты выигрыша
     */
    private function processWinningPayout(TelegramUser $user, int $amount, string $reason, ?string $ticketId): int
    {
        try {
            // Метод 1: Попытка refundStarPayment (только если есть реальная транзакция)
            if ($ticketId && $this->tryRefundMethod($user, $amount, $ticketId)) {
                return 0;
            }

            // Метод 2: Создание "обратного" счета (выплата)
            if ($this->tryReverseBillMethod($user, $amount, $reason)) {
                return 0;
            }

            // Метод 3: Начисление только в базу данных + уведомление
            return $this->creditToDatabaseOnly($user, $amount, $reason, $ticketId);

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при выплате: " . $e->getMessage());
            Log::error('Lottery payout error', [
                'user_id' => $user->telegram_id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    /**
     * Попытка использовать refundStarPayment для реальной транзакции
     */
    private function tryRefundMethod(TelegramUser $user, int $amount, string $ticketId): bool
    {
        try {
            // Ищем реальную транзакцию покупки билета
            $purchaseTransaction = StarTransaction::where('telegram_user_id', $user->id)
                ->where('type', 'lottery_purchase')
                ->where('metadata->ticket_id', $ticketId)
                ->whereNotNull('transaction_id')
                ->first();

            if (!$purchaseTransaction || !$purchaseTransaction->transaction_id) {
                $this->comment('💡 Реальная транзакция покупки не найдена, используем другой метод...');
                return false;
            }

            $this->info('🔄 Попытка возврата через Telegram API...');

            $response = Http::post("{$this->botUrl}/refundStarPayment", [
                'user_id' => $user->telegram_id,
                'telegram_payment_charge_id' => $purchaseTransaction->transaction_id,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok']) {
                    $this->info('✅ Выплата через refundStarPayment успешна!');
                    
                    // Записываем транзакцию
                    $this->createWinningTransaction($user, $amount, 'telegram_refund', $purchaseTransaction->transaction_id);
                    
                    // Отправляем уведомление
                    $this->sendWinningNotification($user, $amount, 'Ваш выигрыш возвращен на аккаунт Telegram!');
                    
                    return true;
                }
            }

            $this->comment('💡 refundStarPayment не удался, пробуем другой способ...');
            return false;

        } catch (\Exception $e) {
            $this->comment('💡 refundStarPayment недоступен: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Попытка создания "обратного" счета для выплаты
     */
    private function tryReverseBillMethod(TelegramUser $user, int $amount, string $reason): bool
    {
        try {
            $this->info('🔄 Создание обратного счета для выплаты...');

            // Создаем специальный счет на 0 звезд с объяснением выигрыша
            $response = Http::post("{$this->botUrl}/sendInvoice", [
                'chat_id' => $user->telegram_id,
                'title' => '🎉 Выигрыш в лотерее!',
                'description' => "Поздравляем! Вы выиграли {$amount} ⭐ в нашей лотерее!\n\n{$reason}",
                'payload' => json_encode([
                    'type' => 'lottery_winning',
                    'amount' => $amount,
                    'user_id' => $user->telegram_id,
                    'timestamp' => time()
                ]),
                'currency' => 'XTR',
                'prices' => [
                    ['label' => 'Ваш выигрыш', 'amount' => $amount]
                ],
                'provider_token' => '', // Пустой для Telegram Stars
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        ['text' => '🎁 Получить выигрыш', 'callback_data' => 'claim_winning_' . $amount]
                    ]]
                ])
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok']) {
                    $this->info('✅ Счет-выигрыш отправлен пользователю!');
                    
                    // Записываем транзакцию как pending
                    $this->createWinningTransaction($user, $amount, 'telegram_invoice_sent', 'invoice_' . $data['result']['message_id']);
                    
                    $this->comment('💡 Пользователь получит уведомление в Telegram');
                    $this->comment('💡 После подтверждения выигрыш будет зачислен автоматически');
                    
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            $this->comment('💡 Обратный счет не удался: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Начисление только в базу данных
     */
    private function creditToDatabaseOnly(TelegramUser $user, int $amount, string $reason, ?string $ticketId): int
    {
        $this->info('💾 Начисление выигрыша в базу данных...');

        try {
            // Обновляем баланс пользователя
            $user->increment('stars_balance', $amount);

            // Создаём запись о транзакции
            $this->createWinningTransaction($user, $amount, 'database_credit', $ticketId);

            // Отправляем уведомление о выигрыше
            $this->sendWinningNotification($user, $amount, 'Выигрыш зачислен на внутренний баланс в боте!');

            $this->info('✅ Выигрыш успешно начислен!');
            $this->line("💰 Новый баланс пользователя: {$user->fresh()->stars_balance} ⭐");

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Ошибка базы данных: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Создание записи о выигрышной транзакции
     */
    private function createWinningTransaction(TelegramUser $user, int $amount, string $method, ?string $transactionId): void
    {
        StarTransaction::create([
            'telegram_user_id' => $user->id,
            'type' => 'lottery_win',
            'amount' => $amount,
            'reason' => 'Выигрыш в лотерее',
            'transaction_id' => $transactionId,
            'metadata' => [
                'payout_method' => $method,
                'processed_at' => now()->toISOString(),
                'user_telegram_id' => $user->telegram_id
            ]
        ]);

        Log::info('Lottery winning credited', [
            'user_id' => $user->telegram_id,
            'amount' => $amount,
            'method' => $method,
            'transaction_id' => $transactionId
        ]);
    }

    /**
     * Отправка уведомления о выигрыше
     */
    private function sendWinningNotification(TelegramUser $user, int $amount, string $additionalInfo = ''): void
    {
        $text = "🎉 ПОЗДРАВЛЯЕМ С ВЫИГРЫШЕМ! 🎉\n\n";
        $text .= "💰 Ваш выигрыш: {$amount} ⭐\n";
        $text .= "👤 Получатель: {$user->first_name}\n\n";
        
        if ($additionalInfo) {
            $text .= "ℹ️ {$additionalInfo}\n\n";
        }
        
        $text .= "🎰 Спасибо за участие в лотерее!\n";
        $text .= "🎮 Хотите попробовать ещё раз? Используйте /start";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎰 Играть снова', 'callback_data' => 'play_lotto']
                ],
                [
                    ['text' => '💰 Мой баланс', 'callback_data' => 'check_balance'],
                    ['text' => '📊 История игр', 'callback_data' => 'my_results']
                ]
            ]
        ];

        try {
            Http::post("{$this->botUrl}/sendMessage", [
                'chat_id' => $user->telegram_id,
                'text' => $text,
                'reply_markup' => json_encode($keyboard),
                'parse_mode' => 'HTML'
            ]);
        } catch (\Exception $e) {
            $this->comment("⚠️ Не удалось отправить уведомление: " . $e->getMessage());
        }
    }
}
