<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestBotStars extends Command
{
    protected $signature = 'bot:test-stars {chat_id?}';
    protected $description = 'Тестирует возможности бота для работы с Telegram Stars';

    private $botToken;
    private $botUrl;

    public function __construct()
    {
        parent::__construct();
        $this->botToken = env('TELEGRAM_BOT_TOKEN', '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
        $this->botUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    public function handle()
    {
        $this->info('Проверяем бота для работы с Telegram Stars...');

        // 1. Проверяем информацию о боте
        $this->info('1. Проверяем информацию о боте...');
        $botInfo = $this->getBotInfo();
        if ($botInfo) {
            $this->info("Бот: @{$botInfo['username']} ({$botInfo['first_name']})");
            $this->info("ID: {$botInfo['id']}");
            $this->info("Может присоединяться к группам: " . ($botInfo['can_join_groups'] ? 'Да' : 'Нет'));
            $this->info("Может читать все сообщения: " . ($botInfo['can_read_all_group_messages'] ? 'Да' : 'Нет'));
        }

        // 2. Проверяем webhook
        $this->info('2. Проверяем webhook...');
        $webhookInfo = $this->getWebhookInfo();
        if ($webhookInfo) {
            if ($webhookInfo['url']) {
                $this->info("Webhook URL: {$webhookInfo['url']}");
                $this->info("Ожидается сертификат: " . ($webhookInfo['has_custom_certificate'] ? 'Да' : 'Нет'));
                $this->info("Количество ожидающих обновлений: {$webhookInfo['pending_update_count']}");
                if (isset($webhookInfo['last_error_date'])) {
                    $this->warn("Последняя ошибка: {$webhookInfo['last_error_message']}");
                }
            } else {
                $this->warn('Webhook не настроен');
            }
        }

        // 3. Тестируем отправку инвойса (если указан chat_id)
        $chatId = $this->argument('chat_id');
        if ($chatId) {
            $this->info("3. Тестируем отправку инвойса в чат {$chatId}...");
            $this->testInvoice($chatId);
        } else {
            $this->info('3. Для тестирования инвойса запустите: php artisan bot:test-stars YOUR_CHAT_ID');
        }

        $this->info('Проверка завершена!');
    }

    private function getBotInfo()
    {
        try {
            $response = Http::get($this->botUrl . '/getMe');
            if ($response->successful()) {
                $data = $response->json();
                return $data['result'] ?? null;
            }
            $this->error('Ошибка получения информации о боте: ' . $response->body());
        } catch (\Exception $e) {
            $this->error('Исключение при получении информации о боте: ' . $e->getMessage());
        }
        return null;
    }

    private function getWebhookInfo()
    {
        try {
            $response = Http::get($this->botUrl . '/getWebhookInfo');
            if ($response->successful()) {
                $data = $response->json();
                return $data['result'] ?? null;
            }
            $this->error('Ошибка получения информации о webhook: ' . $response->body());
        } catch (\Exception $e) {
            $this->error('Исключение при получении информации о webhook: ' . $e->getMessage());
        }
        return null;
    }

    private function testInvoice($chatId)
    {
        try {
            $payload = json_encode([
                'test' => true,
                'timestamp' => time(),
            ]);

            $response = Http::post($this->botUrl . '/sendInvoice', [
                'chat_id' => $chatId,
                'title' => 'Тестовый Star инвойс',
                'description' => 'Это тестовый инвойс для проверки работы с Telegram Stars',
                'payload' => $payload,
                'currency' => 'XTR',
                'prices' => [
                    [
                        'label' => 'Тестовая покупка',
                        'amount' => 1,
                    ]
                ],
            ]);

            if ($response->successful()) {
                $this->info('✅ Тестовый инвойс отправлен успешно!');
                $result = $response->json();
                $this->info('Ответ: ' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error('❌ Ошибка отправки тестового инвойса:');
                $this->error('Статус: ' . $response->status());
                $this->error('Ответ: ' . $response->body());
                
                $errorData = $response->json();
                if (isset($errorData['description'])) {
                    $this->error('Описание ошибки: ' . $errorData['description']);
                }
            }
        } catch (\Exception $e) {
            $this->error('Исключение при отправке тестового инвойса: ' . $e->getMessage());
        }
    }
}
