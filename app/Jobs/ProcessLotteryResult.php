<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\LottoTicket;
use App\Models\TelegramUser;

class ProcessLotteryResult implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ticketId;
    protected $chatId;

    /**
     * Create a new job instance.
     */
    public function __construct($ticketId, $chatId)
    {
        $this->ticketId = $ticketId;
        $this->chatId = $chatId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $ticket = LottoTicket::with(['lottoGame', 'telegramUser'])->find($this->ticketId);
            
            if (!$ticket) {
                Log::error('❌ Ticket not found for lottery result processing', [
                    'ticket_id' => $this->ticketId
                ]);
                return;
            }

            // Проверяем, что билет ещё не был обработан
            if ($ticket->is_winner !== null) {
                Log::info('⚠️ Ticket already processed', [
                    'ticket_id' => $this->ticketId,
                    'is_winner' => $ticket->is_winner
                ]);
                return;
            }

            // Определяем результат лотереи
            $isWinner = $this->determineWinningResult($ticket);
            
            // Обновляем билет
            $winnings = 0;
            if ($isWinner) {
                $winnings = $ticket->lottoGame->getPotentialWinnings();
            }

            $ticket->update([
                'is_winner' => $isWinner,
                'winnings' => $winnings,
                'drawn_at' => now(),
                'status' => 'completed'
            ]);

            // Отправляем результат пользователю
            $this->sendResult($ticket, $isWinner);

            // Если выиграл - начисляем звёзды
            if ($isWinner && $winnings > 0) {
                $this->creditStarsToUser($ticket->telegramUser, $winnings, $ticket);
            }

            Log::info('✅ Lottery result processed', [
                'ticket_id' => $this->ticketId,
                'is_winner' => $isWinner,
                'winnings' => $winnings,
                'user_id' => $ticket->telegram_user_id
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error processing lottery result', [
                'ticket_id' => $this->ticketId,
                'chat_id' => $this->chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Отправляем сообщение об ошибке
            $this->sendErrorMessage();
        }
    }

    /**
     * Определить результат выигрыша
     */
    private function determineWinningResult(LottoTicket $ticket): bool
    {
        $game = $ticket->lottoGame;
        $random = mt_rand(1, 10000) / 10000; // Генерируем случайное число от 0 до 1
        
        Log::info('🎲 Lottery draw result', [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'random_value' => $random,
            'win_chance' => $game->win_chance,
            'is_winner' => $random <= $game->win_chance
        ]);

        return $random <= $game->win_chance;
    }

    /**
     * Отправить результат пользователю
     */
    private function sendResult(LottoTicket $ticket, bool $isWinner): void
    {
        $botToken = env('TELEGRAM_BOT_TOKEN', '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
        $botUrl = "https://api.telegram.org/bot{$botToken}";

        if ($isWinner) {
            $text = "🎉 ПОЗДРАВЛЯЕМ! ВЫ ВЫИГРАЛИ! 🎉\n\n";
            $text .= "🎟️ Билет: {$ticket->ticket_number}\n";
            $text .= "🎰 Игра: {$ticket->lottoGame->name}\n";
            $text .= "💰 Ваш выигрыш: {$ticket->winnings} ⭐\n\n";
            
            // Проверяем как были начислены звезды
            $winningTransaction = \App\Models\StarTransaction::where('telegram_user_id', $ticket->telegram_user_id)
                ->where('type', 'lottery_win')
                ->where('metadata->ticket_id', $ticket->id)
                ->latest()
                ->first();
            
            if ($winningTransaction && isset($winningTransaction->metadata['payout_method'])) {
                if ($winningTransaction->metadata['payout_method'] === 'telegram_refund') {
                    $text .= "✅ Звёзды уже вернулись на ваш аккаунт Telegram!\n";
                    $text .= "💫 Проверьте баланс в настройках Telegram Stars\n\n";
                } else {
                    $text .= "✅ Звёзды зачислены на внутренний баланс бота!\n";
                    $text .= "💫 Используйте /balance для проверки баланса\n";
                    $text .= "🔄 Выигрыш можно использовать для покупки новых билетов\n\n";
                }
            } else {
                $text .= "✅ Звёзды зачислены на ваш баланс!\n\n";
            }
            
            $text .= "🎊 Спасибо за участие в нашей лотерее!\n\n";
            $text .= "🎮 Хотите попробовать ещё раз? Используйте /start";
        } else {
            $text = "😔 К сожалению, в этот раз удача была не на вашей стороне\n\n";
            $text .= "🎟️ Билет: {$ticket->ticket_number}\n";
            $text .= "🎰 Игра: {$ticket->lottoGame->name}\n";
            $text .= "💫 Результат: Проигрыш\n\n";
            $text .= "💪 Не расстраивайтесь! В следующий раз обязательно повезёт!\n";
            $text .= "🍀 Шанс выигрыша: " . ($ticket->lottoGame->win_chance * 100) . "%\n\n";
            $text .= "🎮 Попробуйте ещё раз! Используйте /start";
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎰 Играть снова', 'callback_data' => 'play_lotto'],
                ],
                [
                    ['text' => '� Мой баланс', 'callback_data' => 'check_balance'],
                    ['text' => '�📊 Мои результаты', 'callback_data' => 'my_results'],
                ],
                [
                    ['text' => '🏆 Все результаты', 'callback_data' => 'all_results'],
                ]
            ]
        ];

        Http::post($botUrl . '/sendMessage', [
            'chat_id' => $this->chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * Начислить звёзды пользователю
     */
    private function creditStarsToUser(TelegramUser $user, int $amount, LottoTicket $ticket): void
    {
        try {
            // Метод 1: Попытка возврата через реальную транзакцию покупки
            if ($this->tryRefundOriginalPayment($user, $amount, $ticket)) {
                return;
            }

            // Метод 2: Начисление в базу данных + специальное уведомление
            $this->creditToDatabase($user, $amount, $ticket);

        } catch (\Exception $e) {
            Log::error('❌ Error crediting stars to user', [
                'user_id' => $user->telegram_id,
                'amount' => $amount,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Попытаться возвратить звезды через реальную транзакцию покупки
     */
    private function tryRefundOriginalPayment(TelegramUser $user, int $amount, LottoTicket $ticket): bool
    {
        try {
            // Ищем оригинальную транзакцию покупки билета
            $purchaseTransaction = \App\Models\StarTransaction::where('telegram_user_id', $user->id)
                ->where('type', 'lottery_purchase')
                ->where('metadata->ticket_id', $ticket->id)
                ->whereNotNull('transaction_id')
                ->first();

            if (!$purchaseTransaction || !$purchaseTransaction->transaction_id) {
                Log::info('💡 Original purchase transaction not found for refund', [
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->telegram_id
                ]);
                return false;
            }

            $botToken = env('TELEGRAM_BOT_TOKEN', '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
            $botUrl = "https://api.telegram.org/bot{$botToken}";

            // Попытка возврата через Telegram API
            $response = Http::post($botUrl . '/refundStarPayment', [
                'user_id' => $user->telegram_id,
                'telegram_payment_charge_id' => $purchaseTransaction->transaction_id,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['ok']) && $data['ok']) {
                    // Создаём запись о выигрышной транзакции
                    \App\Models\StarTransaction::create([
                        'telegram_user_id' => $user->id,
                        'type' => 'lottery_win',
                        'amount' => $amount,
                        'reason' => "Выигрыш в лотерее (возврат). Билет: {$ticket->ticket_number}",
                        'transaction_id' => $purchaseTransaction->transaction_id,
                        'metadata' => [
                            'ticket_id' => $ticket->id,
                            'game_id' => $ticket->lotto_game_id,
                            'ticket_number' => $ticket->ticket_number,
                            'payout_method' => 'telegram_refund',
                            'original_transaction_id' => $purchaseTransaction->transaction_id
                        ]
                    ]);

                    Log::info('✅ Stars refunded via Telegram API', [
                        'user_id' => $user->telegram_id,
                        'amount' => $amount,
                        'ticket_id' => $ticket->id,
                        'original_transaction' => $purchaseTransaction->transaction_id
                    ]);

                    return true;
                }
            }

            Log::info('💡 Telegram refund API not successful', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::info('💡 Telegram refund API not available: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Начислить звезды в базу данных
     */
    private function creditToDatabase(TelegramUser $user, int $amount, LottoTicket $ticket): void
    {
        // Обновляем баланс пользователя в базе
        $user->increment('stars_balance', $amount);

        // Создаём запись о транзакции
        \App\Models\StarTransaction::create([
            'telegram_user_id' => $user->id,
            'type' => 'lottery_win',
            'amount' => $amount,
            'reason' => "Выигрыш в лотерее. Билет: {$ticket->ticket_number}",
            'transaction_id' => $ticket->ticket_number,
            'metadata' => [
                'ticket_id' => $ticket->id,
                'game_id' => $ticket->lotto_game_id,
                'ticket_number' => $ticket->ticket_number,
                'game_name' => $ticket->lottoGame->name,
                'payout_method' => 'database_credit'
            ]
        ]);

        Log::info('💰 Stars credited to database', [
            'user_id' => $user->telegram_id,
            'amount' => $amount,
            'new_balance' => $user->fresh()->stars_balance,
            'ticket_id' => $ticket->id
        ]);
    }

    /**
     * Попытаться отправить звёзды через Telegram API
     */
    /**
     * Отправить сообщение об ошибке
     */
    private function sendErrorMessage(): void
    {
        $botToken = env('TELEGRAM_BOT_TOKEN', '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
        $botUrl = "https://api.telegram.org/bot{$botToken}";

        $text = "⚠️ Произошла техническая ошибка при обработке результата лотереи.\n\n";
        $text .= "🔄 Мы уже работаем над исправлением.\n";
        $text .= "💰 Если вы выиграли, средства будут зачислены в течение некоторого времени.\n\n";
        $text .= "📞 При возникновении вопросов обратитесь в поддержку: /support";

        Http::post($botUrl . '/sendMessage', [
            'chat_id' => $this->chatId,
            'text' => $text
        ]);
    }
}
