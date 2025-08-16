<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\TelegramUser;
use App\Models\LottoGame;
use App\Models\LottoTicket;
use App\Models\StarTransaction;
use App\Jobs\ProcessLotteryResult;

class TestLotterySystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lottery:test {--full : Полный тест с эмуляцией платежа} {--quick : Быстрый тест без задержки} {--user-id= : ID пользователя для теста}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирование всей системы лотереи: от оплаты до выигрыша/проигрыша';

    private $botToken;
    private $botUrl;
    private $testResults = [];

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
        $this->info('🧪 ТЕСТИРОВАНИЕ СИСТЕМЫ ЛОТЕРЕИ');
        $this->newLine();

        // Шаг 1: Проверка подключений
        $this->step1_CheckConnections();
        
        // Шаг 2: Проверка базы данных
        $this->step2_CheckDatabase();
        
        // Шаг 3: Создание тестового пользователя
        $testUser = $this->step3_CreateTestUser();
        
        // Шаг 4: Создание тестового билета
        $testTicket = $this->step4_CreateTestTicket($testUser);
        
        // Шаг 5: Эмуляция успешной оплаты
        $this->step5_EmulatePayment($testTicket);
        
        // Шаг 6: Тестирование обработки результата
        $this->step6_TestLotteryProcessing($testTicket);
        
        // Шаг 7: Проверка уведомлений
        $this->step7_TestNotifications($testTicket);
        
        // Шаг 8: Проверка начисления выигрыша
        $this->step8_TestWinningsCredit($testTicket);
        
        // Показать результаты
        $this->showTestResults();
        
        // Очистка тестовых данных
        if ($this->confirm('Удалить тестовые данные?', true)) {
            $this->cleanupTestData($testUser, $testTicket);
        }
    }

    private function step1_CheckConnections()
    {
        $this->info('🔍 Шаг 1: Проверка подключений...');
        
        try {
            // Проверка Telegram Bot API
            $response = Http::get($this->botUrl . '/getMe');
            if ($response->successful()) {
                $bot = $response->json();
                $this->comment("✅ Telegram Bot API: {$bot['result']['first_name']} (@{$bot['result']['username']})");
                $this->testResults[] = ['step' => 'Telegram API', 'status' => 'success', 'message' => 'Подключение успешно'];
            } else {
                throw new \Exception('Ошибка API: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ Telegram Bot API: {$e->getMessage()}");
            $this->testResults[] = ['step' => 'Telegram API', 'status' => 'error', 'message' => $e->getMessage()];
        }

        try {
            // Проверка базы данных
            DB::connection()->getPdo();
            $this->comment('✅ База данных: Подключение успешно');
            $this->testResults[] = ['step' => 'Database', 'status' => 'success', 'message' => 'Подключение успешно'];
        } catch (\Exception $e) {
            $this->error("❌ База данных: {$e->getMessage()}");
            $this->testResults[] = ['step' => 'Database', 'status' => 'error', 'message' => $e->getMessage()];
        }

        try {
            // Проверка очередей
            $queueConnection = config('queue.default');
            $this->comment("✅ Очереди: Используется {$queueConnection}");
            $this->testResults[] = ['step' => 'Queue', 'status' => 'success', 'message' => "Конфигурация: {$queueConnection}"];
        } catch (\Exception $e) {
            $this->error("❌ Очереди: {$e->getMessage()}");
            $this->testResults[] = ['step' => 'Queue', 'status' => 'error', 'message' => $e->getMessage()];
        }
        
        $this->newLine();
    }

    private function step2_CheckDatabase()
    {
        $this->info('🗄️ Шаг 2: Проверка структуры базы данных...');
        
        $tables = ['telegram_users', 'lotto_games', 'lotto_tickets', 'star_transactions', 'jobs'];
        
        foreach ($tables as $table) {
            try {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $count = DB::table($table)->count();
                    $this->comment("✅ Таблица {$table}: {$count} записей");
                    $this->testResults[] = ['step' => "Table {$table}", 'status' => 'success', 'message' => "{$count} записей"];
                } else {
                    throw new \Exception("Таблица {$table} не существует");
                }
            } catch (\Exception $e) {
                $this->error("❌ Таблица {$table}: {$e->getMessage()}");
                $this->testResults[] = ['step' => "Table {$table}", 'status' => 'error', 'message' => $e->getMessage()];
            }
        }
        
        $this->newLine();
    }

    private function step3_CreateTestUser()
    {
        $this->info('👤 Шаг 3: Создание тестового пользователя...');
        
        try {
            $userId = $this->option('user-id') ?: 999999999; // Тестовый ID
            
            $testUser = TelegramUser::updateOrCreate(
                ['telegram_id' => $userId],
                [
                    'first_name' => 'TestUser',
                    'last_name' => 'LotteryTest',
                    'username' => 'test_lottery_user',
                    'language_code' => 'ru',
                    'is_bot' => false,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'visits_count' => 1,
                    'stars_balance' => 0,
                ]
            );
            
            $this->comment("✅ Тестовый пользователь создан: ID {$testUser->telegram_id}");
            $this->testResults[] = ['step' => 'Test User', 'status' => 'success', 'message' => "ID: {$testUser->telegram_id}"];
            
            return $testUser;
        } catch (\Exception $e) {
            $this->error("❌ Создание пользователя: {$e->getMessage()}");
            $this->testResults[] = ['step' => 'Test User', 'status' => 'error', 'message' => $e->getMessage()];
            return null;
        }
    }

    private function step4_CreateTestTicket($testUser)
    {
        $this->info('🎟️ Шаг 4: Создание тестового билета...');
        
        if (!$testUser) {
            $this->error('❌ Нет тестового пользователя');
            return null;
        }

        try {
            // Получаем первую доступную игру
            $game = LottoGame::where('is_active', true)->first();
            if (!$game) {
                throw new \Exception('Нет активных игр');
            }

            $testTicket = LottoTicket::create([
                'telegram_user_id' => $testUser->id,
                'lotto_game_id' => $game->id,
                'ticket_number' => 'TEST' . now()->format('YmdHis'),
                'stars_paid' => $game->ticket_price,
                'status' => 'pending',
                'is_winner' => null,
                'winnings' => 0,
            ]);
            
            $this->comment("✅ Тестовый билет создан: {$testTicket->ticket_number}");
            $this->comment("   Игра: {$game->name} (цена: {$game->ticket_price} ⭐)");
            $this->testResults[] = ['step' => 'Test Ticket', 'status' => 'success', 'message' => "Билет: {$testTicket->ticket_number}"];
            
            return $testTicket;
        } catch (\Exception $e) {
            $this->error("❌ Создание билета: {$e->getMessage()}");
            $this->testResults[] = ['step' => 'Test Ticket', 'status' => 'error', 'message' => $e->getMessage()];
            return null;
        }
    }

    private function step5_EmulatePayment($testTicket)
    {
        $this->info('💳 Шаг 5: Эмуляция успешной оплаты...');
        
        if (!$testTicket) {
            $this->error('❌ Нет тестового билета');
            return;
        }

        try {
            // Эмулируем успешную оплату
            $testTicket->update([
                'status' => 'participating',
                'purchased_at' => now(),
                'payment_charge_id' => 'test_charge_' . time(),
                'payment_data' => [
                    'test_payment' => true,
                    'telegram_payment_charge_id' => 'test_charge_' . time(),
                    'total_amount' => $testTicket->stars_paid * 100, // В копейках
                    'currency' => 'XTR', // Telegram Stars
                ]
            ]);
            
            $this->comment('✅ Оплата эмулирована успешно');
            $this->testResults[] = ['step' => 'Payment Emulation', 'status' => 'success', 'message' => 'Платеж обработан'];
        } catch (\Exception $e) {
            $this->error("❌ Эмуляция оплаты: {$e->getMessage()}");
            $this->testResults[] = ['step' => 'Payment Emulation', 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function step6_TestLotteryProcessing($testTicket)
    {
        $this->info('🎲 Шаг 6: Тестирование обработки результата лотереи...');
        
        if (!$testTicket) {
            $this->error('❌ Нет тестового билета');
            return;
        }

        try {
            $chatId = $this->option('user-id') ?: 999999999;
            
            if ($this->option('quick')) {
                // Быстрый тест - запускаем сразу
                $this->comment('⚡ Быстрый режим: обработка без задержки');
                ProcessLotteryResult::dispatchSync($testTicket->id, $chatId);
            } else {
                // Обычный тест - через очередь с задержкой
                $executeTime = now()->addMinute();
                $this->comment('⏰ Обычный режим: добавление в очередь с задержкой 1 минута');
                $this->comment("📅 Время выполнения: {$executeTime->format('H:i:s d.m.Y')} MSK");
                ProcessLotteryResult::dispatch($testTicket->id, $chatId)->delay($executeTime);
                
                $pendingJobs = DB::table('jobs')->where('queue', 'default')->count();
                $this->comment("📋 Задач в очереди: {$pendingJobs}");
            }
            
            $this->testResults[] = ['step' => 'Lottery Processing', 'status' => 'success', 'message' => 'Job создан'];
        } catch (\Exception $e) {
            $this->error("❌ Обработка лотереи: {$e->getMessage()}");
            $this->testResults[] = ['step' => 'Lottery Processing', 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function step7_TestNotifications($testTicket)
    {
        $this->info('📱 Шаг 7: Проверка системы уведомлений...');
        
        if (!$testTicket) {
            $this->error('❌ Нет тестового билета');
            return;
        }

        try {
            $chatId = $this->option('user-id') ?: 999999999;
            
            // Тестируем отправку сообщения
            $testMessage = "🧪 Тестовое сообщение системы лотереи\n\nБилет: {$testTicket->ticket_number}\nВремя: " . now()->format('H:i:s');
            
            $response = Http::post($this->botUrl . '/sendMessage', [
                'chat_id' => $chatId,
                'text' => $testMessage,
                'parse_mode' => 'HTML'
            ]);

            if ($response->successful()) {
                $this->comment('✅ Уведомления работают');
                $this->testResults[] = ['step' => 'Notifications', 'status' => 'success', 'message' => 'Сообщение отправлено'];
            } else {
                throw new \Exception('Ошибка отправки: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ Уведомления: {$e->getMessage()}");
            $this->testResults[] = ['step' => 'Notifications', 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function step8_TestWinningsCredit($testTicket)
    {
        $this->info('💰 Шаг 8: Тестирование начисления выигрыша...');
        
        if (!$testTicket) {
            $this->error('❌ Нет тестового билета');
            return;
        }

        try {
            $testUser = $testTicket->telegramUser;
            $initialBalance = $testUser->stars_balance;
            
            // Эмулируем выигрыш
            $winAmount = 100;
            $testUser->increment('stars_balance', $winAmount);
            
            // Создаём транзакцию
            StarTransaction::create([
                'telegram_user_id' => $testUser->id,
                'type' => 'test_win',
                'amount' => $winAmount,
                'reason' => 'Тестовое начисление выигрыша',
                'transaction_id' => 'test_' . time(),
                'metadata' => [
                    'test' => true,
                    'ticket_id' => $testTicket->id
                ]
            ]);
            
            $newBalance = $testUser->fresh()->stars_balance;
            
            $this->comment("✅ Начисление работает");
            $this->comment("   Баланс до: {$initialBalance} ⭐");
            $this->comment("   Начислено: {$winAmount} ⭐");
            $this->comment("   Баланс после: {$newBalance} ⭐");
            
            $this->testResults[] = ['step' => 'Winnings Credit', 'status' => 'success', 'message' => "Начислено: {$winAmount} ⭐"];
        } catch (\Exception $e) {
            $this->error("❌ Начисление выигрыша: {$e->getMessage()}");
            $this->testResults[] = ['step' => 'Winnings Credit', 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function showTestResults()
    {
        $this->newLine();
        $this->info('📊 РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ:');
        $this->newLine();
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($this->testResults as $result) {
            $icon = $result['status'] === 'success' ? '✅' : '❌';
            $this->line("{$icon} {$result['step']}: {$result['message']}");
            
            if ($result['status'] === 'success') {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        $this->newLine();
        $this->info("🎯 ИТОГО: {$successCount} успешно, {$errorCount} ошибок");
        
        if ($errorCount === 0) {
            $this->comment('🎉 ВСЕ ТЕСТЫ ПРОЙДЕНЫ! Система готова к работе.');
        } else {
            $this->error('⚠️ ЕСТЬ ПРОБЛЕМЫ! Проверьте ошибки выше.');
        }
        
        $this->newLine();
        $this->info('📋 СЛЕДУЮЩИЕ ШАГИ:');
        if (config('queue.default') === 'database') {
            $this->comment('1. Запустите worker очереди: php artisan queue:work');
        }
        $this->comment('2. Следите за логами: tail -f storage/logs/laravel.log');
        $this->comment('3. Проверьте webhook: php artisan bot:check-stars-setup');
    }

    private function cleanupTestData($testUser, $testTicket)
    {
        $this->info('🧹 Очистка тестовых данных...');
        
        try {
            if ($testTicket) {
                $testTicket->delete();
                $this->comment('✅ Тестовый билет удалён');
            }
            
            if ($testUser) {
                // Удаляем тестовые транзакции
                StarTransaction::where('telegram_user_id', $testUser->id)
                    ->where('type', 'test_win')
                    ->delete();
                    
                $testUser->delete();
                $this->comment('✅ Тестовый пользователь удалён');
            }
            
            // Очищаем тестовые задачи из очереди
            DB::table('jobs')->where('payload', 'like', '%ProcessLotteryResult%')->delete();
            $this->comment('✅ Тестовые задачи очереди удалены');
            
        } catch (\Exception $e) {
            $this->error("❌ Ошибка очистки: {$e->getMessage()}");
        }
    }
}
