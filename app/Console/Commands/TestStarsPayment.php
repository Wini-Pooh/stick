<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestStarsPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:test-stars-payment {user_id? : ID пользователя для теста}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирует отправку Stars платежа и проверяет логи';

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
        $this->info('🧪 Тестирование Stars платежа');
        $this->newLine();

        $userId = $this->argument('user_id') ?: 1107317588; // Ваш ID по умолчанию

        // Отправляем тестовый инвойс
        $this->sendTestInvoice($userId);
        
        // Инструкции для пользователя
        $this->showInstructions();
        
        // Ожидаем действий пользователя
        $this->waitForPayment();
        
        // Проверяем логи
        $this->checkLogs();
    }

    private function sendTestInvoice($userId)
    {
        $this->info("📤 Отправка тестового инвойса пользователю {$userId}...");
        
        $invoice = [
            'chat_id' => $userId,
            'title' => '🧪 Тестовый Stars платеж',
            'description' => 'Тестовая покупка для проверки настройки Stars платежей',
            'payload' => json_encode([
                'test' => true,
                'timestamp' => now()->timestamp,
                'command' => 'bot:test-stars-payment'
            ]),
            'provider_token' => '', // Пустой для Stars
            'currency' => 'XTR',
            'prices' => [
                ['label' => 'Тестовая покупка', 'amount' => 1]
            ]
        ];
        
        try {
            $response = Http::timeout(10)->post($this->botUrl . '/sendInvoice', $invoice);
            $data = $response->json();
            
            if ($data['ok'] ?? false) {
                $this->comment('✅ Тестовый инвойс отправлен успешно');
                $this->line("📋 Message ID: {$data['result']['message_id']}");
                
                Log::info('🧪 Test invoice sent via console command', [
                    'user_id' => $userId,
                    'message_id' => $data['result']['message_id'],
                    'currency' => 'XTR',
                    'amount' => 1
                ]);
            } else {
                $this->error('❌ Ошибка отправки инвойса: ' . ($data['description'] ?? 'Unknown error'));
                return false;
            }
        } catch (\Exception $e) {
            $this->error('❌ Ошибка соединения: ' . $e->getMessage());
            return false;
        }
        
        return true;
    }

    private function showInstructions()
    {
        $this->newLine();
        $this->comment('📋 ИНСТРУКЦИИ ДЛЯ ТЕСТИРОВАНИЯ:');
        $this->line('1. Откройте Telegram и найдите сообщение с инвойсом');
        $this->line('2. Нажмите кнопку "Заплатить ⭐️1"');
        $this->line('3. Завершите платеж (используйте тестовые звезды если есть)');
        $this->line('4. Вернитесь в консоль и нажмите Enter для проверки логов');
        $this->newLine();
        
        $this->info('🔍 Ожидаемая последовательность в логах:');
        $this->line('   [INFO] 🌟 Pre-checkout query received');
        $this->line('   [INFO] ✅ Pre-checkout query approved');
        $this->line('   [INFO] 🌟 Successful payment received');
        $this->line('   [INFO] ✅ Payment confirmed');
        $this->newLine();
    }

    private function waitForPayment()
    {
        $this->comment('⏳ Ожидание завершения платежа...');
        $this->line('Нажмите Enter после завершения платежа или Ctrl+C для отмены');
        
        // Ждем ввода пользователя
        $handle = fopen("php://stdin", "r");
        fgets($handle);
        fclose($handle);
    }

    private function checkLogs()
    {
        $this->info('📋 Проверка логов за последние 5 минут...');
        
        $logFile = storage_path('logs/laravel.log');
        
        if (!file_exists($logFile)) {
            $this->error('❌ Файл логов не найден: ' . $logFile);
            return;
        }
        
        $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recentLogs = [];
        $cutoffTime = now()->subMinutes(5);
        
        foreach (array_reverse($logs) as $line) {
            // Проверяем временную метку лога
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $logTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $matches[1]);
                if ($logTime->lt($cutoffTime)) {
                    break; // Слишком старые логи
                }
            }
            
            // Ищем логи связанные с платежами
            if (strpos($line, 'Pre-checkout') !== false || 
                strpos($line, 'Successful payment') !== false ||
                strpos($line, 'Invoice') !== false ||
                strpos($line, 'payment') !== false ||
                strpos($line, 'webhook') !== false ||
                strpos($line, '🌟') !== false ||
                strpos($line, '🧪') !== false) {
                $recentLogs[] = $line;
            }
        }
        
        if (empty($recentLogs)) {
            $this->error('❌ Логи платежей не найдены за последние 5 минут');
            $this->comment('💡 Это означает, что webhook не получил уведомления от Telegram');
            $this->line('   Возможные причины:');
            $this->line('   - Webhook не настроен для pre_checkout_query и successful_payment');
            $this->line('   - Проблемы с SSL сертификатом');
            $this->line('   - Webhook URL недоступен для Telegram');
        } else {
            $this->comment('📋 НАЙДЕННЫЕ ЛОГИ ПЛАТЕЖЕЙ:');
            foreach (array_reverse($recentLogs) as $log) {
                $this->line('  ' . $log);
            }
            
            // Анализируем логи
            $this->analyzeLogs($recentLogs);
        }
    }

    private function analyzeLogs($logs)
    {
        $this->newLine();
        $this->info('🔍 АНАЛИЗ ЛОГОВ:');
        
        $foundInvoice = false;
        $foundPreCheckout = false;
        $foundSuccessfulPayment = false;
        
        foreach ($logs as $log) {
            if (strpos($log, 'Invoice sent successfully') !== false) {
                $foundInvoice = true;
            }
            if (strpos($log, 'Pre-checkout query received') !== false) {
                $foundPreCheckout = true;
            }
            if (strpos($log, 'Successful payment received') !== false) {
                $foundSuccessfulPayment = true;
            }
        }
        
        $this->line("📤 Отправка инвойса: " . ($foundInvoice ? '✅ Найдена' : '❌ Не найдена'));
        $this->line("🔍 Pre-checkout query: " . ($foundPreCheckout ? '✅ Найден' : '❌ Не найден'));
        $this->line("💰 Successful payment: " . ($foundSuccessfulPayment ? '✅ Найден' : '❌ Не найден'));
        
        if ($foundInvoice && $foundPreCheckout && $foundSuccessfulPayment) {
            $this->newLine();
            $this->info('🎉 ОТЛИЧНО! Stars платежи работают корректно!');
        } elseif ($foundInvoice && !$foundPreCheckout) {
            $this->newLine();
            $this->error('❌ ПРОБЛЕМА: Pre-checkout query не получен');
            $this->comment('💡 Webhook не настроен для обработки pre_checkout_query');
            $this->line('   Запустите: php artisan bot:fix-webhook-stars --delete');
        } elseif ($foundPreCheckout && !$foundSuccessfulPayment) {
            $this->newLine();
            $this->error('❌ ПРОБЛЕМА: Successful payment не получен');
            $this->comment('💡 Возможные причины:');
            $this->line('   - Платеж не был завершен пользователем');
            $this->line('   - Webhook не настроен для successful_payment');
        }
    }
}
