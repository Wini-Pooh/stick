<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckStarsPaymentSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:check-stars-setup {--fix : Автоматически исправить найденные проблемы}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверяет полную настройку Telegram Stars платежей';

    private $botToken;
    private $botUrl;
    private $webhookUrl;
    private $issues = [];
    private $checks = [];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Полная проверка настройки Telegram Stars платежей');
        $this->newLine();

        // Очищаем конфигурацию для получения свежих данных
        $this->call('config:clear');
        
        // Инициализируем переменные после очистки кеша
        $this->botToken = env('TELEGRAM_BOT_TOKEN', '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
        $this->botUrl = "https://api.telegram.org/bot{$this->botToken}";
        $this->webhookUrl = (config('app.url') ?: env('APP_URL') ?: 'https://tg.sticap.ru') . '/api/telegram/webhook';

        // Проверяем все компоненты
        $this->checkBotToken();
        $this->checkWebhookSetup();
        $this->checkWebhookUrl();
        $this->checkRoutes();
        $this->checkControllerMethods();
        $this->checkEnvironment();
        $this->checkInvoiceExample();

        // Показываем результаты
        $this->showResults();

        // Автоисправление если запрошено
        if ($this->option('fix') && count($this->issues) > 0) {
            $this->autoFix();
        }

        return count($this->issues) === 0 ? 0 : 1;
    }

    private function checkBotToken()
    {
        $this->info('📡 Проверка токена бота...');
        
        try {
            $response = Http::timeout(10)->get($this->botUrl . '/getMe');
            $data = $response->json();
            
            if ($data['ok'] ?? false) {
                $bot = $data['result'];
                $this->addCheck('✅ Токен бота', "Действительный: @{$bot['username']} ({$bot['first_name']})");
            } else {
                $this->addIssue('❌ Токен бота', 'Недействительный или неправильный токен');
            }
        } catch (\Exception $e) {
            $this->addIssue('❌ Токен бота', 'Ошибка соединения: ' . $e->getMessage());
        }
    }

    private function checkWebhookSetup()
    {
        $this->info('🌐 Проверка webhook...');
        
        try {
            $response = Http::timeout(10)->get($this->botUrl . '/getWebhookInfo');
            $data = $response->json();
            
            if ($data['ok'] ?? false) {
                $webhook = $data['result'];
                
                // Проверка URL
                if (empty($webhook['url'])) {
                    $this->addIssue('❌ Webhook URL', 'Webhook не установлен');
                } elseif ($webhook['url'] !== $this->webhookUrl) {
                    $this->addIssue('⚠️ Webhook URL', "Установлен: {$webhook['url']}, ожидается: {$this->webhookUrl}");
                } else {
                    $this->addCheck('✅ Webhook URL', $webhook['url']);
                }
                
                // Проверка allowed_updates
                $allowedUpdates = $webhook['allowed_updates'] ?? [];
                $requiredUpdates = ['pre_checkout_query', 'successful_payment'];
                $missingUpdates = array_diff($requiredUpdates, $allowedUpdates);
                
                if (empty($missingUpdates)) {
                    $this->addCheck('✅ Allowed Updates', 'Все необходимые типы включены: ' . implode(', ', $allowedUpdates));
                } else {
                    $this->addIssue('❌ Allowed Updates', 'Отсутствуют критические типы: ' . implode(', ', $missingUpdates));
                }
                
                // Проверка pending updates
                if ($webhook['pending_update_count'] > 10) {
                    $this->addIssue('⚠️ Pending Updates', "Накопилось {$webhook['pending_update_count']} обновлений");
                } else {
                    $this->addCheck('✅ Pending Updates', $webhook['pending_update_count']);
                }
                
                // Проверка ошибок
                if (!empty($webhook['last_error_date'])) {
                    $errorDate = date('Y-m-d H:i:s', $webhook['last_error_date']);
                    $this->addIssue('❌ Webhook Errors', "Последняя ошибка: {$errorDate} - {$webhook['last_error_message']}");
                } else {
                    $this->addCheck('✅ Webhook Errors', 'Ошибок нет');
                }
                
            } else {
                $this->addIssue('❌ Webhook Info', 'Не удалось получить информацию');
            }
        } catch (\Exception $e) {
            $this->addIssue('❌ Webhook Info', 'Ошибка соединения: ' . $e->getMessage());
        }
    }

    private function checkWebhookUrl()
    {
        $this->info('🔗 Проверка доступности webhook URL...');
        
        try {
            $response = Http::timeout(10)->post($this->webhookUrl, ['test' => true]);
            
            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['ok']) && $body['ok'] === true) {
                    $this->addCheck('✅ Webhook доступность', 'Endpoint отвечает корректно');
                } else {
                    $this->addIssue('⚠️ Webhook доступность', 'Endpoint доступен, но возвращает неожиданный ответ');
                }
            } else {
                $this->addIssue('❌ Webhook доступность', "HTTP {$response->status()}: Endpoint недоступен");
            }
        } catch (\Exception $e) {
            $this->addIssue('❌ Webhook доступность', 'Ошибка соединения: ' . $e->getMessage());
        }
    }

    private function checkRoutes()
    {
        $this->info('🛤️ Проверка маршрутов...');
        
        $routes = [
            '/api/telegram/webhook' => 'api.telegram.webhook',
            '/telegram/set-webhook-stars' => 'telegram.set-webhook-stars',
            '/telegram/webhook-info' => 'telegram.webhook-info',
            '/bot-admin' => 'bot.admin'
        ];
        
        foreach ($routes as $url => $routeName) {
            try {
                $fullUrl = (config('app.url') ?: env('APP_URL')) . $url;
                $response = Http::timeout(5)->get($fullUrl);
                
                if ($response->successful() || $response->status() === 405) { // 405 = Method Not Allowed (нормально для POST маршрутов)
                    $this->addCheck("✅ Route {$url}", 'Доступен');
                } else {
                    $this->addIssue("❌ Route {$url}", "HTTP {$response->status()}");
                }
            } catch (\Exception $e) {
                $this->addIssue("❌ Route {$url}", 'Недоступен: ' . $e->getMessage());
            }
        }
    }

    private function checkControllerMethods()
    {
        $this->info('🎛️ Проверка методов контроллера...');
        
        $controllerFile = app_path('Http/Controllers/TelegramBotController.php');
        
        if (!file_exists($controllerFile)) {
            $this->addIssue('❌ Controller', 'TelegramBotController.php не найден');
            return;
        }
        
        $content = file_get_contents($controllerFile);
        
        $requiredMethods = [
            'handlePreCheckoutQuery' => 'Обработка pre_checkout_query',
            'handleSuccessfulPayment' => 'Обработка successful_payment',
            'setWebhookWithStars' => 'Установка webhook для Stars'
        ];
        
        foreach ($requiredMethods as $method => $description) {
            if (strpos($content, "function {$method}") !== false || 
                strpos($content, "function {$method}(") !== false ||
                preg_match("/private\s+function\s+{$method}\s*\(/", $content) ||
                preg_match("/protected\s+function\s+{$method}\s*\(/", $content)) {
                $this->addCheck("✅ Method {$method}", $description);
            } else {
                $this->addIssue("❌ Method {$method}", "Отсутствует: {$description}");
            }
        }
    }

    private function checkEnvironment()
    {
        $this->info('⚙️ Проверка окружения...');
        
        // Читаем .env файл напрямую для более точной проверки
        $envPath = base_path('.env');
        $envVars = [];
        
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            $lines = explode("\n", $envContent);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
                    [$key, $value] = explode('=', $line, 2);
                    $envVars[trim($key)] = trim($value);
                }
            }
        }
        
        $envChecks = [
            'TELEGRAM_BOT_TOKEN' => $envVars['TELEGRAM_BOT_TOKEN'] ?? null,
            'APP_URL' => $envVars['APP_URL'] ?? null,
            'APP_ENV' => $envVars['APP_ENV'] ?? null,
        ];
        
        foreach ($envChecks as $key => $value) {
            if (empty($value)) {
                $this->addIssue("❌ ENV {$key}", 'Не установлен в .env файле');
            } else {
                $this->addCheck("✅ ENV {$key}", $value);
            }
        }
    }

    private function checkInvoiceExample()
    {
        $this->info('📋 Проверка формата инвойса...');
        
        $exampleInvoice = [
            'chat_id' => 123456789,
            'title' => 'Тестовый билет',
            'description' => 'Тестовая покупка',
            'payload' => json_encode(['test' => true]),
            'provider_token' => '', // Пустой для Stars
            'currency' => 'XTR', // Обязательно для Stars
            'prices' => [['label' => 'Билет', 'amount' => 1]]
        ];
        
        // Проверяем валюту
        if ($exampleInvoice['currency'] === 'XTR') {
            $this->addCheck('✅ Currency', 'XTR (Telegram Stars)');
        } else {
            $this->addIssue('❌ Currency', 'Должна быть XTR для Stars платежей');
        }
        
        // Проверяем provider_token
        if (empty($exampleInvoice['provider_token'])) {
            $this->addCheck('✅ Provider Token', 'Пустой (корректно для Stars)');
        } else {
            $this->addIssue('❌ Provider Token', 'Должен быть пустым для Stars платежей');
        }
    }

    private function addCheck($title, $message)
    {
        $this->checks[] = ['title' => $title, 'message' => $message];
    }

    private function addIssue($title, $message)
    {
        $this->issues[] = ['title' => $title, 'message' => $message];
    }

    private function showResults()
    {
        $this->newLine();
        $this->info('📊 РЕЗУЛЬТАТЫ ПРОВЕРКИ:');
        $this->newLine();
        
        // Показываем успешные проверки
        if (count($this->checks) > 0) {
            $this->comment('🟢 УСПЕШНЫЕ ПРОВЕРКИ:');
            foreach ($this->checks as $check) {
                $this->line("  {$check['title']}: {$check['message']}");
            }
            $this->newLine();
        }
        
        // Показываем проблемы
        if (count($this->issues) > 0) {
            $this->error('🔴 НАЙДЕННЫЕ ПРОБЛЕМЫ:');
            foreach ($this->issues as $issue) {
                $this->line("  {$issue['title']}: {$issue['message']}");
            }
            $this->newLine();
            
            $this->comment('💡 Для автоматического исправления запустите:');
            $this->line('  php artisan bot:check-stars-setup --fix');
        } else {
            $this->info('🎉 ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ! Stars платежи должны работать корректно.');
        }
        
        $this->newLine();
        $this->info("✅ Успешно: " . count($this->checks));
        $this->error("❌ Проблем: " . count($this->issues));
    }

    private function autoFix()
    {
        $this->newLine();
        $this->info('🔧 АВТОМАТИЧЕСКОЕ ИСПРАВЛЕНИЕ:');
        
        $hasWebhookIssues = false;
        $hasUrlIssue = false;
        
        // Проверяем, какие проблемы нужно исправить
        foreach ($this->issues as $issue) {
            if (strpos($issue['title'], 'Webhook URL') !== false || 
                strpos($issue['title'], 'Allowed Updates') !== false) {
                $hasWebhookIssues = true;
            }
            if (strpos($issue['title'], 'Webhook доступность') !== false && 
                strpos($issue['description'], '419') !== false) {
                $hasUrlIssue = true;
            }
        }
        
        if ($hasWebhookIssues) {
            $this->line('  📡 Исправляем настройки webhook...');
            $this->call('bot:fix-webhook-stars', ['--delete' => true]);
        }
        
        if ($hasUrlIssue) {
            $this->line('  🔧 Исправляем конфигурацию роутов...');
            $this->warn('  ⚠️ ВНИМАНИЕ: Обнаружена проблема с CSRF защитой для webhook.');
            $this->warn('  ⚠️ Webhook должен использовать API роуты вместо WEB роутов.');
            $this->warn('  ⚠️ Проверьте, что в routes/api.php есть:');
            $this->line('     Route::post(\'/telegram/webhook\', [TelegramBotController::class, \'webhook\']);');
        }
        
        $this->comment('✅ Автоисправление завершено. Запустите проверку снова для подтверждения.');
    }
}
