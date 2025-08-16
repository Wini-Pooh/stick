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
            $text .= "✨ Звёзды уже зачислены на ваш аккаунт!\n";
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
                    ['text' => '📊 Мои результаты', 'callback_data' => 'my_results'],
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
                    'game_name' => $ticket->lottoGame->name
                ]
            ]);

            // Пытаемся отправить звёзды через Telegram API (если возможно)
            $this->tryGiftStarsViaTelegram($user, $amount, $ticket);

            Log::info('💰 Stars credited to user', [
                'user_id' => $user->telegram_id,
                'amount' => $amount,
                'new_balance' => $user->fresh()->stars_balance,
                'ticket_id' => $ticket->id
            ]);

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
     * Попытаться отправить звёзды через Telegram API
     */
    private function tryGiftStarsViaTelegram(TelegramUser $user, int $amount, LottoTicket $ticket): void
    {
        try {
            $botToken = env('TELEGRAM_BOT_TOKEN', '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
            $botUrl = "https://api.telegram.org/bot{$botToken}";

            // Попытка отправить подарок звёзд (если поддерживается API)
            $response = Http::post($botUrl . '/sendGift', [
                'user_id' => $user->telegram_id,
                'gift_id' => 'star_gift_' . $amount, // Псевдо ID подарка
                'text' => "🎉 Ваш выигрыш в лотерее!\nБилет: {$ticket->ticket_number}\nВыигрыш: {$amount} ⭐"
            ]);

            if (!$response->successful()) {
                Log::info('ℹ️ Gift stars via Telegram API not available, using database balance', [
                    'user_id' => $user->telegram_id,
                    'amount' => $amount,
                    'response' => $response->json()
                ]);
            }

        } catch (\Exception $e) {
            Log::info('ℹ️ Telegram Stars gifting not supported, using database balance', [
                'user_id' => $user->telegram_id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
        }
    }

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
