<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetupHostingEnvironment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:setup-hosting {--force : Принудительная перезапись .env}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Настройка окружения для хостинга с правильными переменными';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Настройка окружения для хостинга');
        $this->newLine();

        // Проверяем текущий .env
        $envPath = base_path('.env');
        $envExists = file_exists($envPath);

        if ($envExists && !$this->option('force')) {
            $this->line('📁 Файл .env уже существует');
            if (!$this->confirm('Обновить переменные окружения?')) {
                $this->comment('Операция отменена');
                return;
            }
        }

        // Определяем правильные значения для хостинга
        $envVars = [
            'APP_NAME' => 'TgStick',
            'APP_ENV' => 'production',
            'APP_KEY' => $this->getAppKey(),
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://tg.sticap.ru',
            
            'LOG_CHANNEL' => 'stack',
            'LOG_DEPRECATIONS_CHANNEL' => 'null',
            'LOG_LEVEL' => 'info',
            
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'boost113ic_tg_sticap_ru',
            'DB_USERNAME' => 'boost113ic_tg_sticap_ru',
            'DB_PASSWORD' => '',
            
            'BROADCAST_DRIVER' => 'log',
            'CACHE_DRIVER' => 'file',
            'FILESYSTEM_DISK' => 'local',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'file',
            'SESSION_LIFETIME' => '120',
            
            'MEMCACHED_HOST' => '127.0.0.1',
            
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PASSWORD' => 'null',
            'REDIS_PORT' => '6379',
            
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => 'mailpit',
            'MAIL_PORT' => '1025',
            'MAIL_USERNAME' => 'null',
            'MAIL_PASSWORD' => 'null',
            'MAIL_ENCRYPTION' => 'null',
            'MAIL_FROM_ADDRESS' => '"hello@example.com"',
            'MAIL_FROM_NAME' => '"${APP_NAME}"',
            
            'AWS_ACCESS_KEY_ID' => '',
            'AWS_SECRET_ACCESS_KEY' => '',
            'AWS_DEFAULT_REGION' => 'us-east-1',
            'AWS_BUCKET' => '',
            'AWS_USE_PATH_STYLE_ENDPOINT' => 'false',
            
            'PUSHER_APP_ID' => '',
            'PUSHER_APP_KEY' => '',
            'PUSHER_APP_SECRET' => '',
            'PUSHER_HOST' => '',
            'PUSHER_PORT' => '443',
            'PUSHER_SCHEME' => 'https',
            'PUSHER_APP_CLUSTER' => 'mt1',
            
            'VITE_APP_NAME' => '"${APP_NAME}"',
            'VITE_PUSHER_APP_KEY' => '"${PUSHER_APP_KEY}"',
            'VITE_PUSHER_HOST' => '"${PUSHER_HOST}"',
            'VITE_PUSHER_PORT' => '"${PUSHER_PORT}"',
            'VITE_PUSHER_SCHEME' => '"${PUSHER_SCHEME}"',
            'VITE_PUSHER_APP_CLUSTER' => '"${PUSHER_APP_CLUSTER}"',
            
            // Telegram специфичные переменные
            'TELEGRAM_BOT_TOKEN' => '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4',
            'TELEGRAM_BOT_USERNAME' => 'Sticap_bot',
        ];

        // Создаем или обновляем .env файл
        $this->createEnvFile($envVars);

        // Проверяем APP_KEY
        $this->checkAppKey();

        // Устанавливаем webhook
        $this->setupWebhook();

        $this->newLine();
        $this->info('✅ Настройка окружения завершена!');
        $this->comment('💡 Теперь запустите: php8.1 artisan bot:check-stars-setup');
    }

    private function getAppKey()
    {
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
            if (preg_match('/APP_KEY=(.+)/', $content, $matches)) {
                return trim($matches[1]);
            }
        }
        
        // Генерируем новый ключ
        return 'base64:' . base64_encode(random_bytes(32));
    }

    private function createEnvFile($envVars)
    {
        $this->info('📝 Создание .env файла...');

        $envContent = '';
        foreach ($envVars as $key => $value) {
            $envContent .= "{$key}={$value}\n";
        }

        file_put_contents(base_path('.env'), $envContent);
        $this->comment('✅ .env файл создан');
    }

    private function checkAppKey()
    {
        $this->info('🔑 Проверка APP_KEY...');
        
        if (empty(config('app.key'))) {
            $this->comment('🔧 Генерация нового APP_KEY...');
            $this->call('key:generate');
        } else {
            $this->comment('✅ APP_KEY установлен');
        }
    }

    private function setupWebhook()
    {
        $this->info('🌐 Настройка webhook...');
        
        try {
            $botToken = '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4';
            $webhookUrl = 'https://tg.sticap.ru/api/telegram/webhook';
            
            $this->line("🔗 Webhook URL: {$webhookUrl}");
            
            // Удаляем старый webhook
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/deleteWebhook");
            
            if ($response->successful()) {
                $this->comment('🗑️ Старый webhook удален');
            }
            
            // Устанавливаем новый webhook с поддержкой Stars
            $allowedUpdates = [
                'message',
                'edited_message', 
                'callback_query',
                'inline_query',
                'pre_checkout_query',    // Критично для Stars!
                'successful_payment'     // Критично для Stars!
            ];
            
            $response = Http::timeout(10)->asForm()->post("https://api.telegram.org/bot{$botToken}/setWebhook", [
                'url' => $webhookUrl,
                'allowed_updates' => json_encode($allowedUpdates),
                'drop_pending_updates' => 'true'
            ]);
            
            $data = $response->json();
            
            if ($data['ok'] ?? false) {
                $this->comment('✅ Webhook установлен с поддержкой Stars');
                $this->line('   📋 Включены: ' . implode(', ', $allowedUpdates));
            } else {
                $this->error('❌ Ошибка установки webhook: ' . ($data['description'] ?? 'Неизвестная ошибка'));
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Ошибка соединения: ' . $e->getMessage());
        }
    }
}
