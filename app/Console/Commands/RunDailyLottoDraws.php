<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\LottoGame;
use App\Models\LottoDraw;
use App\Models\LottoTicket;
use App\Models\TelegramUser;

class RunDailyLottoDraws extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lotto:draw {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проводит ежедневные розыгрыши лото';

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
        $date = $this->argument('date') ? \Carbon\Carbon::parse($this->argument('date')) : today();
        
        $this->info("Запуск розыгрышей лото на {$date->format('d.m.Y')}");

        $games = LottoGame::active()->get();
        $totalDraws = 0;
        $totalWinners = 0;
        $totalWinnings = 0;

        foreach ($games as $game) {
            $this->info("Обработка игры: {$game->name}");

            // Получаем или создаём розыгрыш на указанную дату
            $draw = LottoDraw::firstOrCreate([
                'lotto_game_id' => $game->id,
                'draw_date' => $date,
            ], [
                'status' => 'upcoming',
                'total_tickets' => 0,
                'total_pool' => 0,
            ]);

            // Проверяем, не был ли розыгрыш уже проведён
            if ($draw->status === 'completed') {
                $this->warn("Розыгрыш для игры {$game->name} уже был проведён");
                continue;
            }

            // Получаем все билеты для этой игры на указанную дату
            $tickets = LottoTicket::where('lotto_game_id', $game->id)
                ->whereDate('purchased_at', $date)
                ->where('status', 'participating')
                ->get();

            if ($tickets->isEmpty()) {
                $this->info("Нет билетов для игры {$game->name}");
                $draw->update([
                    'status' => 'completed',
                    'executed_at' => now(),
                    'total_tickets' => 0,
                    'total_pool' => 0,
                ]);
                continue;
            }

            $this->info("Найдено {$tickets->count()} билетов");

            // Проводим розыгрыш
            $result = $draw->conductDraw();

            if ($result) {
                $totalDraws++;
                $totalWinners += $draw->winners_count;
                $totalWinnings += $draw->total_winnings;

                $this->info("Розыгрыш проведён: {$draw->winners_count} победителей из {$draw->total_tickets} участников");

                // Отправляем уведомления победителям
                $this->notifyWinners($draw, $tickets->where('is_winner', true));
            } else {
                $this->error("Ошибка при проведении розыгрыша для игры {$game->name}");
            }
        }

        $this->info("Розыгрыши завершены:");
        $this->info("- Всего розыгрышей: {$totalDraws}");
        $this->info("- Всего победителей: {$totalWinners}");
        $this->info("- Общая сумма выигрышей: {$totalWinnings} ⭐");

        // Отправляем общую статистику в канал/группу (если настроено)
        $this->sendDailyStatistics($date, $totalDraws, $totalWinners, $totalWinnings);

        Log::info('Daily lotto draws completed', [
            'date' => $date->format('Y-m-d'),
            'total_draws' => $totalDraws,
            'total_winners' => $totalWinners,
            'total_winnings' => $totalWinnings,
        ]);

        return 0;
    }

    /**
     * Отправить уведомления победителям
     */
    private function notifyWinners($draw, $winningTickets)
    {
        foreach ($winningTickets as $ticket) {
            $telegramUser = $ticket->telegramUser;
            if (!$telegramUser) continue;

            $message = "🎉 ПОЗДРАВЛЯЕМ!\n\n";
            $message .= "🎟️ Ваш билет №{$ticket->ticket_number} выиграл!\n";
            $message .= "🎰 Игра: {$draw->lottoGame->name}\n";
            $message .= "💰 Выигрыш: {$ticket->winnings} ⭐\n\n";
            $message .= "💫 Звёзды автоматически зачислены на ваш баланс в Telegram!\n";
            $message .= "🎲 Удачи в следующих розыгрышах!";

            $this->sendTelegramMessage($telegramUser->telegram_id, $message);
        }
    }

    /**
     * Отправить ежедневную статистику
     */
    private function sendDailyStatistics($date, $totalDraws, $totalWinners, $totalWinnings)
    {
        $channelId = env('TELEGRAM_CHANNEL_ID'); // Можно добавить в .env ID канала для статистики
        
        if (!$channelId) return;

        $message = "📊 СТАТИСТИКА РОЗЫГРЫШЕЙ\n";
        $message .= "📅 Дата: {$date->format('d.m.Y')}\n\n";
        $message .= "🎰 Проведено розыгрышей: {$totalDraws}\n";
        $message .= "🏆 Всего победителей: {$totalWinners}\n";
        $message .= "💰 Общая сумма выигрышей: {$totalWinnings} ⭐\n\n";
        $message .= "🎲 Участвуйте в розыгрышах каждый день!";

        $this->sendTelegramMessage($channelId, $message);
    }

    /**
     * Отправить сообщение в Telegram
     */
    private function sendTelegramMessage($chatId, $text)
    {
        try {
            $response = Http::post($this->botUrl . '/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            if (!$response->successful()) {
                Log::warning('Failed to send Telegram message', [
                    'chat_id' => $chatId,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception sending Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
