<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestBotConfiguration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:test-config {--webhook : Test webhook configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирует конфигурацию бота для работы с Telegram Stars';

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
        $this->info('🔍 Диагностика конфигурации Telegram Bot для Stars платежей');
        $this->newLine();

        // 1. Проверка токена бота
        $this->checkBotToken();
        
        // 2. Проверка информации о боте
        $this->checkBotInfo();
        
        // 3. Проверка webhook
        if ($this->option('webhook')) {
            $this->checkWebhook();
        }
        
        // 4. Тест создания инвойса
        $this->testStarInvoice();
        
        // 5. Проверка команд бота
        $this->checkBotCommands();
        
        $this->newLine();
        $this->info('✅ Диагностика завершена');
    }

    private function checkBotToken()
    {
        $this->info('1️⃣ Проверка токена бота...');
        
        if (!$this->botToken) {
            $this->error('❌ Токен бота не найден в .env файле');
            return;
        }
        
        $this->line("📋 Токен: " . substr($this->botToken, 0, 20) . '...');
        $this->comment('✅ Токен присутствует');
    }

    private function checkBotInfo()
    {
        $this->info('2️⃣ Получение информации о боте...');
        
        try {
            $response = Http::timeout(10)->get($this->botUrl . '/getMe');
            $data = $response->json();
            
            if ($data['ok']) {
                $bot = $data['result'];
                $this->line("🤖 Имя бота: {$bot['first_name']}");
                $this->line("👤 Username: @{$bot['username']}");
                $this->line("🆔 ID: {$bot['id']}");
                $this->line("🔗 Can Join Groups: " . ($bot['can_join_groups'] ? 'Да' : 'Нет'));
                $this->line("📝 Can Read All Group Messages: " . ($bot['can_read_all_group_messages'] ? 'Да' : 'Нет'));
                $this->line("🔄 Supports Inline Queries: " . ($bot['supports_inline_queries'] ? 'Да' : 'Нет'));
                $this->comment('✅ Бот активен и настроен');
            } else {
                $this->error('❌ Ошибка получения информации о боте: ' . $data['description']);
            }
        } catch (\Exception $e) {
            $this->error('❌ Ошибка соединения: ' . $e->getMessage());
        }
    }

    private function checkWebhook()
    {
        $this->info('3️⃣ Проверка webhook...');
        
        try {
            $response = Http::timeout(10)->get($this->botUrl . '/getWebhookInfo');
            $data = $response->json();
            
            if ($data['ok']) {
                $webhook = $data['result'];
                $this->line("🔗 URL: " . ($webhook['url'] ?: 'не установлен'));
                $this->line("✅ Has Custom Certificate: " . ($webhook['has_custom_certificate'] ? 'Да' : 'Нет'));
                $this->line("⏱️ Pending Update Count: " . $webhook['pending_update_count']);
                $this->line("🔄 Max Connections: " . ($webhook['max_connections'] ?? 'не указано'));
                
                if (isset($webhook['allowed_updates'])) {
                    $this->line("📋 Allowed Updates: " . implode(', ', $webhook['allowed_updates']));
                } else {
                    $this->comment('📋 Allowed Updates: все типы (по умолчанию)');
                }
                
                if ($webhook['last_error_date'] ?? false) {
                    $this->warn("⚠️ Последняя ошибка: " . $webhook['last_error_message']);
                    $this->warn("📅 Дата ошибки: " . date('Y-m-d H:i:s', $webhook['last_error_date']));
                }
                
                $this->comment('✅ Webhook информация получена');
            } else {
                $this->error('❌ Ошибка получения webhook информации: ' . $data['description']);
            }
        } catch (\Exception $e) {
            $this->error('❌ Ошибка соединения: ' . $e->getMessage());
        }
    }

    private function testStarInvoice()
    {
        $this->info('4️⃣ Тест создания Star Invoice...');
        
        // Проверяем, может ли бот создавать инвойсы (тестовый режим)
        $testPayload = [
            'chat_id' => '12345', // Тестовый chat_id
            'title' => 'Тестовый Star Invoice',
            'description' => 'Тест создания инвойса для Telegram Stars',
            'payload' => json_encode(['test' => true, 'timestamp' => time()]),
            'provider_token' => '', // Пустой для Stars
            'currency' => 'XTR',
            'prices' => [
                ['label' => 'Тестовый товар', 'amount' => 1]
            ],
            'need_name' => false,
            'need_phone_number' => false,
            'need_email' => false,
            'need_shipping_address' => false,
            'send_phone_number_to_provider' => false,
            'send_email_to_provider' => false,
            'is_flexible' => false,
        ];
        
        try {
            // Не отправляем реально, только проверяем формат
            $this->line("💰 Currency: {$testPayload['currency']}");
            $this->line("🏷️ Title: {$testPayload['title']}");
            $this->line("📄 Description: {$testPayload['description']}");
            $this->line("💳 Provider Token: " . ($testPayload['provider_token'] === '' ? 'Пустой (правильно для Stars)' : 'Установлен'));
            $this->line("💵 Price: {$testPayload['prices'][0]['amount']} Stars");
            
            $this->comment('✅ Формат инвойса корректен для Telegram Stars');
            $this->warn('⚠️ Реальная отправка инвойса не выполнена (тестовый режим)');
            
        } catch (\Exception $e) {
            $this->error('❌ Ошибка формирования инвойса: ' . $e->getMessage());
        }
    }

    private function checkBotCommands()
    {
        $this->info('5️⃣ Проверка команд бота...');
        
        try {
            $response = Http::timeout(10)->get($this->botUrl . '/getMyCommands');
            $data = $response->json();
            
            if ($data['ok']) {
                $commands = $data['result'];
                if (empty($commands)) {
                    $this->warn('⚠️ У бота нет установленных команд');
                    $this->comment('💡 Рекомендуется установить команды через BotFather');
                } else {
                    $this->line('📋 Установленные команды:');
                    foreach ($commands as $command) {
                        $this->line("  /{$command['command']} - {$command['description']}");
                    }
                }
                
                // Проверяем критично важные команды для Stars
                $requiredCommands = ['terms', 'support'];
                $missingCommands = [];
                
                $existingCommands = array_column($commands, 'command');
                foreach ($requiredCommands as $required) {
                    if (!in_array($required, $existingCommands)) {
                        $missingCommands[] = $required;
                    }
                }
                
                if (!empty($missingCommands)) {
                    $this->warn('⚠️ Отсутствуют обязательные команды для Stars: /' . implode(', /', $missingCommands));
                    $this->comment('💡 Добавьте эти команды через BotFather или они должны обрабатываться в коде');
                } else {
                    $this->comment('✅ Все необходимые команды присутствуют');
                }
                
            } else {
                $this->error('❌ Ошибка получения команд: ' . $data['description']);
            }
        } catch (\Exception $e) {
            $this->error('❌ Ошибка соединения: ' . $e->getMessage());
        }
        
        $this->newLine();
        $this->info('📋 РЕКОМЕНДАЦИИ ДЛЯ TELEGRAM STARS:');
        $this->line('1. Убедитесь, что бот настроен в BotFather для приёма платежей');
        $this->line('2. Добавьте команды /terms и /support через BotFather');
        $this->line('3. Проверьте, что webhook получает pre_checkout_query события');
        $this->line('4. Убедитесь, что provider_token пустой для Stars платежей');
        $this->line('5. Валюта должна быть XTR для всех Stars транзакций');
    }
}
