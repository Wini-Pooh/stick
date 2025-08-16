<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TelegramUser;
use App\Models\LottoTicket;
use App\Models\StarTransaction;
use App\Jobs\ProcessLotteryResult;

class TestWinningPayout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lottery:test-winning-payout 
                           {--user-id= : ID пользователя для теста}
                           {--amount=10 : Сумма выигрыша для теста}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирование системы выплаты выигрышей в лотерее';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id') ?: '999999999';
        $winAmount = (int) $this->option('amount');

        $this->info('🧪 Тестирование системы выплаты выигрышей');
        $this->newLine();

        $this->info("👤 Тестовый пользователь: {$userId}");
        $this->info("💰 Сумма выигрыша: {$winAmount} ⭐");
        $this->newLine();

        // Шаг 1: Создаём тестового пользователя
        $testUser = $this->createTestUser($userId);
        if (!$testUser) {
            return 1;
        }

        // Шаг 2: Создаём тестовый билет
        $testTicket = $this->createTestTicket($testUser, $winAmount);
        if (!$testTicket) {
            return 1;
        }

        // Шаг 3: Эмулируем покупку (для возможности возврата)
        $this->emulateTicketPurchase($testTicket);

        // Шаг 4: Тестируем выплату выигрыша
        $this->testWinningPayout($testTicket, $testUser, $winAmount);

        // Шаг 5: Проверяем результаты
        $this->checkResults($testUser, $testTicket);

        $this->newLine();
        $this->info('✅ Тестирование системы выплат завершено!');

        return 0;
    }

    /**
     * Создание тестового пользователя
     */
    private function createTestUser($userId)
    {
        $this->info('1️⃣ Создание тестового пользователя...');

        try {
            $testUser = TelegramUser::where('telegram_id', $userId)->first();
            
            if (!$testUser) {
                $testUser = TelegramUser::create([
                    'telegram_id' => $userId,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'username' => 'test_user_' . $userId,
                    'language_code' => 'ru',
                    'is_bot' => false,
                    'stars_balance' => 0
                ]);
            }

            $this->comment("✅ Пользователь: {$testUser->first_name} {$testUser->last_name} (ID: {$testUser->telegram_id})");
            return $testUser;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка создания пользователя: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Создание тестового билета
     */
    private function createTestTicket($testUser, $winAmount)
    {
        $this->info('2️⃣ Создание тестового билета...');

        try {
            // Ищем активную игру или создаём новую
            $game = \App\Models\LottoGame::where('is_active', true)->first();
            
            if (!$game) {
                $game = \App\Models\LottoGame::create([
                    'name' => 'Звёздное Лото (Тест)',
                    'description' => 'Тестовая игра',
                    'ticket_price' => 1,
                    'max_tickets' => 1000,
                    'win_chance' => 1.0, // 100% шанс выигрыша для теста
                    'is_active' => true,
                    'start_time' => now(),
                    'end_time' => now()->addHours(24)
                ]);
            }

            $ticket = LottoTicket::create([
                'lotto_game_id' => $game->id,
                'telegram_user_id' => $testUser->id,
                'ticket_number' => 'TEST-' . time(),
                'payment_charge_id' => 'test_charge_' . time(),
                'status' => 'paid',
                'is_winner' => null // Не определён пока
            ]);

            $this->comment("✅ Билет создан: {$ticket->ticket_number}");
            return $ticket;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка создания билета: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Эмуляция покупки билета для создания транзакции возврата
     */
    private function emulateTicketPurchase($ticket)
    {
        $this->info('3️⃣ Эмуляция покупки билета...');

        try {
            StarTransaction::create([
                'telegram_user_id' => $ticket->telegramUser->id,
                'type' => 'lottery_purchase',
                'amount' => -1, // Списание 1 звезды за билет
                'reason' => "Покупка билета лотереи: {$ticket->ticket_number}",
                'transaction_id' => 'telegram_charge_' . time(), // Имитация реального ID транзакции
                'metadata' => [
                    'ticket_id' => $ticket->id,
                    'game_id' => $ticket->lotto_game_id,
                    'ticket_number' => $ticket->ticket_number
                ]
            ]);

            $this->comment("✅ Транзакция покупки создана");

        } catch (\Exception $e) {
            $this->comment("⚠️ Не удалось создать транзакцию покупки: " . $e->getMessage());
        }
    }

    /**
     * Тестирование выплаты выигрыша
     */
    private function testWinningPayout($ticket, $testUser, $winAmount)
    {
        $this->info('4️⃣ Тестирование выплаты выигрыша...');

        try {
            // Обновляем билет как выигрышный
            $ticket->update([
                'is_winner' => true,
                'winnings' => $winAmount,
                'drawn_at' => now(),
                'status' => 'completed'
            ]);

            // Запускаем задачу обработки выигрыша
            $job = new ProcessLotteryResult($ticket->id, $testUser->telegram_id);
            $job->handle();

            $this->comment("✅ Задача обработки выигрыша выполнена");

        } catch (\Exception $e) {
            $this->error("❌ Ошибка обработки выигрыша: " . $e->getMessage());
        }
    }

    /**
     * Проверка результатов
     */
    private function checkResults($testUser, $ticket)
    {
        $this->info('5️⃣ Проверка результатов...');

        try {
            // Обновляем данные пользователя
            $testUser->refresh();

            // Проверяем баланс
            $this->comment("💰 Баланс пользователя: {$testUser->stars_balance} ⭐");

            // Проверяем транзакции выигрыша
            $winTransactions = StarTransaction::where('telegram_user_id', $testUser->id)
                ->where('type', 'lottery_win')
                ->get();

            $this->comment("🎉 Транзакций выигрыша: " . $winTransactions->count());

            foreach ($winTransactions as $transaction) {
                $method = $transaction->metadata['payout_method'] ?? 'unknown';
                $this->comment("   - {$transaction->amount} ⭐ (метод: {$method})");
            }

            // Проверяем статус билета
            $ticket->refresh();
            $this->comment("🎟️ Статус билета: {$ticket->status}");
            $this->comment("🏆 Выигрыш: " . ($ticket->is_winner ? "ДА ({$ticket->winnings} ⭐)" : "НЕТ"));

        } catch (\Exception $e) {
            $this->error("❌ Ошибка проверки результатов: " . $e->getMessage());
        }
    }
}
