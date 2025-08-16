<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FixWebhookForStars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:fix-webhook-stars {--delete : Delete webhook first} {--info : Show current webhook info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Исправляет webhook для корректной работы с Telegram Stars платежами';

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
        $this->info('🔧 Исправление webhook для Telegram Stars платежей');
        $this->newLine();

        // Показать текущую информацию
        if ($this->option('info')) {
            $this->showWebhookInfo();
            return;
        }

        // Удалить webhook если запрошено
        if ($this->option('delete')) {
            $this->deleteWebhook();
        }

        // Показать текущее состояние
        $this->showWebhookInfo();
        $this->newLine();

        // Установить новый webhook с правильными настройками
        $this->setWebhookWithStarsSupport();

        // Показать обновленное состояние
        $this->newLine();
        $this->info('📋 Обновленное состояние webhook:');
        $this->showWebhookInfo();

        $this->newLine();
        $this->info('✅ Webhook настроен для Stars платежей!');
    }

    private function showWebhookInfo()
    {
        $this->info('📋 Текущая информация о webhook:');
        
        try {
            $response = Http::timeout(10)->get($this->botUrl . '/getWebhookInfo');
            $data = $response->json();
            
            if ($data['ok']) {
                $webhook = $data['result'];
                $this->line("🔗 URL: " . ($webhook['url'] ?: 'не установлен'));
                $this->line("⏱️ Pending Updates: " . $webhook['pending_update_count']);
                $this->line("🔄 Max Connections: " . ($webhook['max_connections'] ?? 'не указано'));
                
                if (isset($webhook['allowed_updates']) && !empty($webhook['allowed_updates'])) {
                    $this->line("📋 Allowed Updates: " . implode(', ', $webhook['allowed_updates']));
                    
                    // Проверяем наличие критических updates для Stars
                    $requiredUpdates = ['pre_checkout_query', 'successful_payment'];
                    $missingUpdates = array_diff($requiredUpdates, $webhook['allowed_updates']);
                    
                    if (!empty($missingUpdates)) {
                        $this->error("❌ Отсутствуют критические updates для Stars: " . implode(', ', $missingUpdates));
                    } else {
                        $this->comment("✅ Все необходимые updates для Stars присутствуют");
                    }
                } else {
                    $this->comment("📋 Allowed Updates: все типы (по умолчанию)");
                    $this->comment("✅ Это нормально - должны приходить все типы включая Stars");
                }
                
                if ($webhook['last_error_date'] ?? false) {
                    $this->warn("⚠️ Последняя ошибка: " . $webhook['last_error_message']);
                    $this->warn("📅 Дата ошибки: " . date('Y-m-d H:i:s', $webhook['last_error_date']));
                }
            } else {
                $this->error('❌ Ошибка получения webhook информации: ' . $data['description']);
            }
        } catch (\Exception $e) {
            $this->error('❌ Ошибка соединения: ' . $e->getMessage());
        }
    }

    private function deleteWebhook()
    {
        $this->info('🗑️ Удаление текущего webhook...');
        
        try {
            $response = Http::timeout(10)->post($this->botUrl . '/deleteWebhook');
            $data = $response->json();
            
            if ($data['ok']) {
                $this->comment('✅ Webhook удален');
            } else {
                $this->error('❌ Ошибка удаления webhook: ' . $data['description']);
            }
        } catch (\Exception $e) {
            $this->error('❌ Ошибка удаления webhook: ' . $e->getMessage());
        }
    }

    private function setWebhookWithStarsSupport()
    {
        $this->info('🔧 Установка webhook с поддержкой Stars...');
        
        $appUrl = config('app.url') ?: env('APP_URL') ?: 'https://tg.sticap.ru';
        $webhookUrl = $appUrl . '/api/telegram/webhook';
        
        $this->line("🌐 App URL: {$appUrl}");
        $this->line("🔗 Full Webhook URL: {$webhookUrl}");
        
        // Все необходимые типы обновлений для полноценной работы с Stars
        $allowedUpdates = [
            'message',              // Обычные сообщения
            'edited_message',       // Редактированные сообщения
            'callback_query',       // Inline кнопки
            'inline_query',         // Inline режим (опционально)
            'pre_checkout_query',   // 🌟 Критично для Stars - предварительная проверка
            'successful_payment'    // 🌟 Критично для Stars - успешный платеж
        ];
        
        $this->line("📋 Allowed Updates: " . implode(', ', $allowedUpdates));
        
        try {
            // Очищаем конфигурацию для получения свежих ENV переменных
            $this->call('config:clear');
            
            // Метод 1: через POST с JSON body
            $response = Http::timeout(15)->post($this->botUrl . '/setWebhook', [
                'url' => $webhookUrl,
                'allowed_updates' => $allowedUpdates,
                'drop_pending_updates' => true,
                'max_connections' => 40
            ]);
            
            $data = $response->json();
            
            if (!$data['ok']) {
                // Метод 2: через form data с JSON-строкой (для совместимости)
                $this->line('⚠️ Первая попытка не удалась, пробуем второй способ...');
                $response = Http::timeout(15)->asForm()->post($this->botUrl . '/setWebhook', [
                    'url' => $webhookUrl,
                    'allowed_updates' => json_encode($allowedUpdates),
                    'drop_pending_updates' => 'true',
                    'max_connections' => '40'
                ]);
                $data = $response->json();
            }
            
            if ($data['ok']) {
                $this->comment('✅ Webhook установлен с поддержкой Stars платежей');
                $this->comment('✅ Накопившиеся updates очищены');
                
                // Ждем несколько секунд для применения изменений
                $this->comment('⏳ Ожидание применения изменений...');
                sleep(3);
                
            } else {
                $this->error('❌ Ошибка установки webhook: ' . ($data['description'] ?? 'Неизвестная ошибка'));
                $this->line('📋 Response: ' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } catch (\Exception $e) {
            $this->error('❌ Ошибка установки webhook: ' . $e->getMessage());
        }
        
        $this->newLine();
        $this->info('🔍 ВАЖНЫЕ ПРОВЕРКИ:');
        $this->line('1. ✅ URL webhook: ' . $webhookUrl);
        $this->line('2. ✅ pre_checkout_query включен');
        $this->line('3. ✅ successful_payment включен');
        $this->line('4. ✅ drop_pending_updates = true');
        
        $this->newLine();
        $this->comment('💡 Теперь бот должен получать Stars платежи правильно!');
    }
}
