<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\TelegramUser;
use App\Models\LottoTicket;
use App\Models\StarTransaction;

class DiagnoseWinningPayout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lottery:diagnose-payout {--user-id= : ID пользователя для диагностики} {--ticket-id= : ID билета для диагностики}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Диагностика проблем с выплатой выигрышей в лотерее';

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
        $this->info('🔍 Диагностика системы выплаты выигрышей');
        $this->newLine();

        $userId = $this->option('user-id');
        $ticketId = $this->option('ticket-id');

        if ($userId) {
            $this->diagnoseUserWinnings($userId);
        } elseif ($ticketId) {
            $this->diagnoseTicketPayout($ticketId);
        } else {
            $this->diagnoseSystemWide();
        }

        $this->newLine();
        $this->info('📋 Рекомендации для исправления:');
        $this->line('1. Проверьте логи: php artisan queue:monitor');
        $this->line('2. Ручная выплата: php artisan stars:manage gift USER_ID AMOUNT --reason="Выигрыш"');
        $this->line('3. Тест выплаты: php artisan lottery:test-winning-payout --user-id=USER_ID');
    }

    private function diagnoseUserWinnings($userId)
    {
        $this->info("👤 Диагностика выигрышей пользователя: {$userId}");
        $this->newLine();

        // Проверяем пользователя
        $user = TelegramUser::where('telegram_id', $userId)->first();
        if (!$user) {
            $this->error("❌ Пользователь не найден в базе данных");
            return;
        }

        $this->line("✅ Пользователь найден: {$user->first_name} {$user->last_name}");
        $this->line("💰 Баланс в базе: {$user->stars_balance} звезд");
        $this->newLine();

        // Проверяем выигрышные билеты
        $winningTickets = LottoTicket::where('telegram_user_id', $user->id)
            ->where('is_winner', true)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($winningTickets->isEmpty()) {
            $this->warn("⚠️ Выигрышных билетов не найдено");
        } else {
            $this->info("🎟️ Найдено выигрышных билетов: " . $winningTickets->count());
            
            foreach ($winningTickets as $ticket) {
                $this->line("  📝 Билет #{$ticket->id}: {$ticket->ticket_number}");
                $this->line("  💰 Выигрыш: {$ticket->winnings} звезд");
                $this->line("  📅 Дата: {$ticket->drawn_at}");
                $this->line("  📊 Статус: {$ticket->status}");
                $this->newLine();
            }
        }

        // Проверяем транзакции выигрышей
        $winTransactions = StarTransaction::where('telegram_user_id', $user->id)
            ->where('type', 'lottery_win')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($winTransactions->isEmpty()) {
            $this->error("❌ Транзакции выигрышей не найдены!");
            $this->warn("   Это означает, что система не зачислила выигрыш");
        } else {
            $this->info("💳 Найдено транзакций выигрышей: " . $winTransactions->count());
            
            foreach ($winTransactions as $transaction) {
                $this->line("  🔄 Транзакция #{$transaction->id}");
                $this->line("  💰 Сумма: {$transaction->amount} звезд");
                $this->line("  📅 Дата: {$transaction->created_at}");
                $this->line("  📋 Причина: {$transaction->reason}");
                $payout_method = $transaction->metadata['payout_method'] ?? 'unknown';
                $this->line("  🔧 Метод выплаты: {$payout_method}");
                $this->newLine();
            }
        }

        // Проверяем джобы в очереди
        $this->checkPendingJobs($user->id);
    }

    private function diagnoseTicketPayout($ticketId)
    {
        $this->info("🎟️ Диагностика выплаты по билету: {$ticketId}");
        $this->newLine();

        $ticket = LottoTicket::with(['telegramUser', 'lottoGame'])->find($ticketId);
        if (!$ticket) {
            $this->error("❌ Билет не найден");
            return;
        }

        $this->line("✅ Билет найден: {$ticket->ticket_number}");
        $this->line("🎰 Игра: {$ticket->lottoGame->name}");
        $this->line("👤 Пользователь: {$ticket->telegramUser->first_name}");
        $this->line("🏆 Выиграл: " . ($ticket->is_winner ? 'Да' : 'Нет'));
        $this->line("💰 Размер выигрыша: {$ticket->winnings} звезд");
        $this->line("📊 Статус: {$ticket->status}");
        $this->line("📅 Обработан: {$ticket->drawn_at}");
        $this->newLine();

        if ($ticket->is_winner) {
            // Проверяем транзакцию выигрыша
            $winTransaction = StarTransaction::where('telegram_user_id', $ticket->telegram_user_id)
                ->where('type', 'lottery_win')
                ->where('metadata->ticket_id', $ticket->id)
                ->first();

            if (!$winTransaction) {
                $this->error("❌ Транзакция выигрыша не найдена!");
                $this->warn("   Выигрыш не был зачислен пользователю");
                
                // Предлагаем ручное исправление
                $this->askForManualPayout($ticket);
            } else {
                $this->info("✅ Транзакция выигрыша найдена");
                $this->line("  💰 Сумма: {$winTransaction->amount} звезд");
                $this->line("  📅 Дата: {$winTransaction->created_at}");
                $payout_method = $winTransaction->metadata['payout_method'] ?? 'unknown';
                $this->line("  🔧 Метод: {$payout_method}");
            }
        }
    }

    private function diagnoseSystemWide()
    {
        $this->info("🌐 Системная диагностика выплат выигрышей");
        $this->newLine();

        // Статистика выигрышных билетов
        $totalWinningTickets = LottoTicket::where('is_winner', true)->count();
        $unpaidWinnings = LottoTicket::where('is_winner', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('star_transactions')
                    ->whereColumn('star_transactions.telegram_user_id', 'lotto_tickets.telegram_user_id')
                    ->where('star_transactions.type', 'lottery_win')
                    ->whereRaw('JSON_EXTRACT(star_transactions.metadata, "$.ticket_id") = lotto_tickets.id');
            })->count();

        $this->line("🎟️ Всего выигрышных билетов: {$totalWinningTickets}");
        $this->line("❌ Неоплаченных выигрышей: {$unpaidWinnings}");
        $this->newLine();

        if ($unpaidWinnings > 0) {
            $this->error("⚠️ Обнаружены неоплаченные выигрыши!");
            
            // Показываем подробности
            $unpaidTickets = LottoTicket::with(['telegramUser', 'lottoGame'])
                ->where('is_winner', true)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('star_transactions')
                        ->whereColumn('star_transactions.telegram_user_id', 'lotto_tickets.telegram_user_id')
                        ->where('star_transactions.type', 'lottery_win')
                        ->whereRaw('JSON_EXTRACT(star_transactions.metadata, "$.ticket_id") = lotto_tickets.id');
                })
                ->limit(5)
                ->get();

            foreach ($unpaidTickets as $ticket) {
                $this->line("  📝 Билет #{$ticket->id}: {$ticket->ticket_number}");
                $this->line("  👤 Пользователь: {$ticket->telegramUser->telegram_id}");
                $this->line("  💰 Выигрыш: {$ticket->winnings} звезд");
                $this->newLine();
            }

            if ($this->confirm('Хотите исправить все неоплаченные выигрыши?')) {
                $this->fixUnpaidWinnings();
            }
        } else {
            $this->info("✅ Все выигрыши оплачены");
        }

        // Проверяем очередь
        $this->checkQueueStatus();
    }

    private function checkPendingJobs($userId = null)
    {
        $this->info("🔄 Проверка очереди задач...");
        
        $pendingJobs = DB::table('jobs')->count();
        $this->line("📋 Задач в очереди: {$pendingJobs}");
        
        if ($pendingJobs > 0) {
            $this->warn("⚠️ Есть необработанные задачи в очереди");
            $this->line("   Запустите: php artisan queue:work");
        } else {
            $this->info("✅ Очередь пуста");
        }
    }

    private function checkQueueStatus()
    {
        $this->info("🔄 Статус системы очереди:");
        
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        
        $this->line("📋 Ожидающих задач: {$pendingJobs}");
        $this->line("❌ Провалившихся задач: {$failedJobs}");
        
        if ($failedJobs > 0) {
            $this->error("⚠️ Есть провалившиеся задачи!");
            $this->line("   Посмотрите: php artisan queue:failed");
            $this->line("   Повторите: php artisan queue:retry all");
        }
    }

    private function askForManualPayout($ticket)
    {
        if ($this->confirm("Хотите вручную выплатить выигрыш?")) {
            $this->manualPayout($ticket);
        }
    }

    private function manualPayout($ticket)
    {
        try {
            $user = $ticket->telegramUser;
            $amount = $ticket->winnings;

            // Начисляем в базу данных
            $user->increment('stars_balance', $amount);

            // Создаём транзакцию
            StarTransaction::create([
                'telegram_user_id' => $user->id,
                'type' => 'lottery_win',
                'amount' => $amount,
                'reason' => "Ручная выплата выигрыша. Билет: {$ticket->ticket_number}",
                'transaction_id' => "manual_payout_{$ticket->id}",
                'metadata' => [
                    'ticket_id' => $ticket->id,
                    'game_id' => $ticket->lotto_game_id,
                    'ticket_number' => $ticket->ticket_number,
                    'payout_method' => 'manual_database_credit',
                    'admin_action' => true
                ]
            ]);

            $this->info("✅ Выигрыш зачислен вручную!");
            $this->line("💰 {$amount} звезд зачислено пользователю {$user->telegram_id}");
            $this->line("🏦 Новый баланс: {$user->fresh()->stars_balance} звезд");

            // Отправляем уведомление пользователю
            $this->sendPayoutNotification($user, $amount, $ticket);

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при ручной выплате: " . $e->getMessage());
        }
    }

    private function sendPayoutNotification($user, $amount, $ticket)
    {
        try {
            $text = "🎉 Ваш выигрыш зачислен!\n\n";
            $text .= "🎟️ Билет: {$ticket->ticket_number}\n";
            $text .= "💰 Выигрыш: {$amount} ⭐\n";
            $text .= "🏦 Звезды зачислены на ваш внутренний баланс бота\n\n";
            $text .= "💡 Используйте команду /balance для проверки баланса";

            $response = Http::post("{$this->botUrl}/sendMessage", [
                'chat_id' => $user->telegram_id,
                'text' => $text
            ]);

            if ($response->successful()) {
                $this->info("✅ Уведомление отправлено пользователю");
            } else {
                $this->warn("⚠️ Не удалось отправить уведомление");
            }

        } catch (\Exception $e) {
            $this->warn("⚠️ Ошибка при отправке уведомления: " . $e->getMessage());
        }
    }

    private function fixUnpaidWinnings()
    {
        $this->info("🔧 Исправление всех неоплаченных выигрышей...");

        $unpaidTickets = LottoTicket::with(['telegramUser', 'lottoGame'])
            ->where('is_winner', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('star_transactions')
                    ->whereColumn('star_transactions.telegram_user_id', 'lotto_tickets.telegram_user_id')
                    ->where('star_transactions.type', 'lottery_win')
                    ->whereRaw('JSON_EXTRACT(star_transactions.metadata, "$.ticket_id") = lotto_tickets.id');
            })->get();

        $fixed = 0;
        foreach ($unpaidTickets as $ticket) {
            try {
                $this->manualPayout($ticket);
                $fixed++;
            } catch (\Exception $e) {
                $this->error("❌ Ошибка при исправлении билета #{$ticket->id}: " . $e->getMessage());
            }
        }

        $this->info("✅ Исправлено выигрышей: {$fixed}");
    }
}
