<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ForceFixStarsSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:force-fix-stars {--test : Запустить тест после исправления}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Принудительное исправление всех проблем с Telegram Stars';

    private $botToken = '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4';
    private $botUrl;

    public function __construct()
    {
        parent::__construct();
        $this->botUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 ПРИНУДИТЕЛЬНОЕ ИСПРАВЛЕНИЕ TELEGRAM STARS');
        $this->newLine();

        // Шаг 1: Очистка кешей
        $this->step1_ClearCaches();
        
        // Шаг 2: Проверка и создание .env
        $this->step2_FixEnvironment();
        
        // Шаг 3: Принудительная установка webhook
        $this->step3_ForceWebhook();
        
        // Шаг 4: Проверка результата
        $this->step4_VerifySetup();
        
        // Шаг 5: Тестирование (если запрошено)
        if ($this->option('test')) {
            $this->step5_RunTest();
        }

        $this->newLine();
        $this->info('🎉 ПРИНУДИТЕЛЬНОЕ ИСПРАВЛЕНИЕ ЗАВЕРШЕНО!');
    }

    private function step1_ClearCaches()
    {
        $this->info('🧹 Шаг 1: Очистка кешей...');
        
        try {
            $this->call('config:clear');
            $this->call('cache:clear');
            $this->call('route:clear');
            $this->call('view:clear');
            $this->comment('✅ Кеши очищены');
        } catch (\Exception $e) {
            $this->warn('⚠️ Не удалось очистить некоторые кеши: ' . $e->getMessage());
        }
    }

    private function step2_FixEnvironment()
    {
        $this->info('⚙️ Шаг 2: Исправление .env файла...');
        
        $envPath = base_path('.env');
        $envLines = [];
        
        // Читаем существующий .env если есть
        if (file_exists($envPath)) {
            $envLines = file($envPath, FILE_IGNORE_NEW_LINES);
        }
        
        // Обязательные переменные для хостинга
        $requiredVars = [
            'APP_NAME' => 'TgStick',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://tg.sticap.ru',
            'TELEGRAM_BOT_TOKEN' => $this->botToken,
            'TELEGRAM_BOT_USERNAME' => 'Sticap_bot',
        ];
        
        // Обновляем или добавляем переменные
        $updated = [];
        foreach ($envLines as $line) {
            $processed = false;
            foreach ($requiredVars as $key => $value) {
                if (strpos($line, $key . '=') === 0) {
                    $updated[] = $key . '=' . $value;
                    $processed = true;
                    unset($requiredVars[$key]);
                    break;
                }
            }
            if (!$processed && trim($line)) {
                $updated[] = $line;
            }
        }
        
        // Добавляем недостающие переменные
        foreach ($requiredVars as $key => $value) {
            $updated[] = $key . '=' . $value;
        }
        
        // Записываем обновленный .env
        file_put_contents($envPath, implode("\n", $updated) . "\n");
        $this->comment('✅ .env файл обновлен');
        
        // Повторно очищаем config cache
        $this->call('config:clear');
    }

    private function step3_ForceWebhook()
    {
        $this->info('🌐 Шаг 3: Принудительная установка webhook...');
        
        $webhookUrl = 'https://tg.sticap.ru/api/telegram/webhook';
        
        // Шаг 3.1: Удаляем текущий webhook
        $this->line('🗑️ Удаление старого webhook...');
        try {
            Http::timeout(10)->post($this->botUrl . '/deleteWebhook', [
                'drop_pending_updates' => true
            ]);
            $this->comment('✅ Старый webhook удален');
            sleep(2); // Ждем применения
        } catch (\Exception $e) {
            $this->warn('⚠️ Проблема с удалением: ' . $e->getMessage());
        }
        
        // Шаг 3.2: Устанавливаем новый webhook (несколько попыток)
        $allowedUpdates = [
            'message',
            'edited_message', 
            'callback_query',
            'inline_query',
            'pre_checkout_query',
            'successful_payment'
        ];
        
        $attempts = 0;
        $maxAttempts = 3;
        $success = false;
        
        while ($attempts < $maxAttempts && !$success) {
            $attempts++;
            $this->line("🔄 Попытка #{$attempts} установки webhook...");
            
            try {
                // Пробуем разные методы
                if ($attempts == 1) {
                    // Метод 1: JSON body
                    $response = Http::timeout(15)->post($this->botUrl . '/setWebhook', [
                        'url' => $webhookUrl,
                        'allowed_updates' => $allowedUpdates,
                        'drop_pending_updates' => true,
                        'max_connections' => 40
                    ]);
                } else {
                    // Метод 2: Form data
                    $response = Http::timeout(15)->asForm()->post($this->botUrl . '/setWebhook', [
                        'url' => $webhookUrl,
                        'allowed_updates' => json_encode($allowedUpdates),
                        'drop_pending_updates' => 'true',
                        'max_connections' => '40'
                    ]);
                }
                
                $data = $response->json();
                
                if ($data['ok'] ?? false) {
                    $this->comment("✅ Webhook установлен (попытка #{$attempts})");
                    $success = true;
                    sleep(3); // Ждем применения
                } else {
                    $this->error("❌ Попытка #{$attempts} неудачна: " . ($data['description'] ?? 'Unknown error'));
                    if ($attempts < $maxAttempts) sleep(2);
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Попытка #{$attempts} ошибка: " . $e->getMessage());
                if ($attempts < $maxAttempts) sleep(2);
            }
        }
        
        if (!$success) {
            $this->error('❌ Не удалось установить webhook после всех попыток');
        }
    }

    private function step4_VerifySetup()
    {
        $this->info('🔍 Шаг 4: Проверка результата...');
        
        try {
            $response = Http::timeout(10)->get($this->botUrl . '/getWebhookInfo');
            $data = $response->json();
            
            if ($data['ok'] ?? false) {
                $webhook = $data['result'];
                
                $this->line('🔗 URL: ' . ($webhook['url'] ?: 'не установлен'));
                $this->line('⏱️ Pending Updates: ' . $webhook['pending_update_count']);
                
                if (isset($webhook['allowed_updates']) && !empty($webhook['allowed_updates'])) {
                    $updates = $webhook['allowed_updates'];
                    $this->line('📋 Allowed Updates: ' . implode(', ', $updates));
                    
                    $required = ['pre_checkout_query', 'successful_payment'];
                    $missing = array_diff($required, $updates);
                    
                    if (empty($missing)) {
                        $this->comment('✅ Все критические updates для Stars присутствуют!');
                    } else {
                        $this->error('❌ Отсутствуют: ' . implode(', ', $missing));
                    }
                } else {
                    $this->comment('📋 Allowed Updates: все типы (по умолчанию) - это нормально');
                }
                
            } else {
                $this->error('❌ Ошибка получения webhook info: ' . ($data['description'] ?? 'Unknown'));
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Ошибка проверки: ' . $e->getMessage());
        }
    }

    private function step5_RunTest()
    {
        $this->info('🧪 Шаг 5: Запуск теста платежей...');
        $this->call('bot:test-stars-payment', ['user_id' => '1107317588']);
    }
}
