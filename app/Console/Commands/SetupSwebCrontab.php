<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupSwebCrontab extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:setup-sweb-crontab 
                           {--dry-run : Только показать команды без выполнения}
                           {--with-monitoring : Добавить команды мониторинга}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Настройка Crontab специально для SWEB хостинга (boost113ic/tg_sticap_ru)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🌐 Настройка Crontab для SWEB хостинга');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $withMonitoring = $this->option('with-monitoring');

        // Проверяем окружение
        if (!$this->checkSwebEnvironment()) {
            return 1;
        }

        // Генерируем команды crontab
        $crontabEntries = $this->generateSwebCrontabEntries($withMonitoring);

        // Показываем команды
        $this->showCrontabEntries($crontabEntries);

        if ($dryRun) {
            $this->info('🔍 Режим проверки (--dry-run) - команды не выполнены');
            $this->showManualInstructions($crontabEntries);
            return 0;
        }

        // Подтверждение
        if (!$this->confirm('Установить эти команды в crontab?')) {
            $this->info('Операция отменена');
            return 0;
        }

        // Подготовка окружения
        $this->prepareSwebEnvironment();

        // Установка crontab
        return $this->installSwebCrontab($crontabEntries);
    }

    /**
     * Проверка окружения SWEB
     */
    private function checkSwebEnvironment(): bool
    {
        $this->info('🔍 Проверка окружения SWEB...');

        // Проверяем что мы в правильной папке
        $currentDir = basename(getcwd());
        if ($currentDir !== 'tg_sticap_ru') {
            $this->warn("⚠️ Текущая папка: {$currentDir}");
            $this->comment('💡 Убедитесь что вы находитесь в папке tg_sticap_ru');
            
            if (!$this->confirm('Продолжить установку?')) {
                return false;
            }
        }

        // Проверяем artisan
        if (!file_exists('artisan')) {
            $this->error('❌ Файл artisan не найден в текущей папке');
            return false;
        }

        // Проверяем PHP 8.1
        $phpCheck = shell_exec('php8.1 --version 2>/dev/null');
        if (empty($phpCheck)) {
            $this->error('❌ PHP 8.1 не найден');
            $this->comment('💡 Убедитесь что PHP 8.1 доступен на хостинге');
            return false;
        } else {
            $phpVersion = explode("\n", $phpCheck)[0];
            $this->comment("✅ PHP найден: {$phpVersion}");
        }

        // Проверяем crontab
        $crontabCheck = shell_exec('which crontab 2>/dev/null');
        if (empty($crontabCheck)) {
            $this->error('❌ Crontab не доступен');
            return false;
        }

        $this->comment('✅ Окружение SWEB готово');
        return true;
    }

    /**
     * Генерация команд crontab для SWEB
     */
    private function generateSwebCrontabEntries($withMonitoring): array
    {
        $entries = [
            'queue_worker' => [
                'schedule' => '* * * * *',
                'command' => 'cd tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3',
                'output' => '>> logs/queue-worker.log 2>&1',
                'description' => 'Laravel Queue Worker - каждую минуту'
            ],
            'fix_delayed' => [
                'schedule' => '*/5 * * * *',
                'command' => 'cd tg_sticap_ru && php8.1 artisan queue:fix-delayed --force',
                'output' => '>> logs/queue-fix.log 2>&1',
                'description' => 'Исправление зависших задач - каждые 5 минут'
            ]
        ];

        if ($withMonitoring) {
            $entries['queue_monitor'] = [
                'schedule' => '*/30 * * * *',
                'command' => 'cd tg_sticap_ru && php8.1 artisan queue:monitor',
                'output' => '>> logs/queue-monitor.log 2>&1',
                'description' => 'Мониторинг очереди - каждые 30 минут'
            ];

            $entries['log_cleanup'] = [
                'schedule' => '0 2 * * *',
                'command' => 'find tg_sticap_ru/storage/logs/*.log -type f -mtime +7 -delete',
                'output' => '2>/dev/null',
                'description' => 'Очистка старых логов - ежедневно в 2:00'
            ];
        }

        return $entries;
    }

    /**
     * Показать команды crontab
     */
    private function showCrontabEntries($entries): void
    {
        $this->info('📋 Команды для crontab:');
        $this->newLine();

        foreach ($entries as $entry) {
            $this->comment("# {$entry['description']}");
            $fullCommand = "{$entry['schedule']} {$entry['command']} {$entry['output']}";
            $this->line($fullCommand);
            $this->newLine();
        }
    }

    /**
     * Показать инструкции для ручной установки
     */
    private function showManualInstructions($entries): void
    {
        $this->newLine();
        $this->info('📝 Для ручной установки:');
        $this->newLine();
        
        $this->comment('1. Откройте crontab:');
        $this->line('   crontab -e');
        $this->newLine();
        
        $this->comment('2. Добавьте эти строки:');
        foreach ($entries as $entry) {
            $this->line("   # {$entry['description']}");
            $this->line("   {$entry['schedule']} {$entry['command']} {$entry['output']}");
        }
        $this->newLine();
        
        $this->comment('3. Сохраните (Ctrl+X, Y, Enter)');
        $this->comment('4. Проверьте: crontab -l');
    }

    /**
     * Подготовка окружения SWEB
     */
    private function prepareSwebEnvironment(): void
    {
        $this->info('🔧 Подготовка окружения...');

        // Создаем папку для логов
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
            $this->comment('✅ Создана папка logs/');
        }

        // Проверяем права на artisan
        if (file_exists('artisan')) {
            chmod('artisan', 0755);
            $this->comment('✅ Установлены права на artisan');
        }

        // Создаем базовые лог файлы
        $logFiles = ['queue-worker.log', 'queue-fix.log', 'queue-monitor.log'];
        foreach ($logFiles as $logFile) {
            $path = "logs/{$logFile}";
            if (!file_exists($path)) {
                touch($path);
                chmod($path, 0664);
            }
        }

        $this->comment('✅ Окружение подготовлено');
    }

    /**
     * Установка crontab для SWEB
     */
    private function installSwebCrontab($entries): int
    {
        $this->info('📝 Установка crontab...');

        try {
            // Получаем текущий crontab
            $currentCrontab = shell_exec('crontab -l 2>/dev/null') ?: '';
            
            // Маркеры для наших записей
            $startMarker = '# SWEB Laravel Queue Worker - Auto Generated';
            $endMarker = '# End SWEB Laravel Queue Worker';
            
            // Формируем новые записи
            $newEntries = "\n{$startMarker}\n";
            foreach ($entries as $entry) {
                $newEntries .= "# {$entry['description']}\n";
                $newEntries .= "{$entry['schedule']} {$entry['command']} {$entry['output']}\n";
            }
            $newEntries .= "{$endMarker}\n";

            // Проверяем существующие записи
            if (strpos($currentCrontab, $startMarker) !== false) {
                $this->warn('⚠️ Записи SWEB Laravel Queue Worker уже существуют');
                
                if ($this->confirm('Обновить существующие записи?')) {
                    // Удаляем старые записи
                    $pattern = "/{$startMarker}.*?{$endMarker}\n/s";
                    $currentCrontab = preg_replace($pattern, '', $currentCrontab);
                } else {
                    $this->info('Операция отменена');
                    return 0;
                }
            }

            // Объединяем
            $fullCrontab = trim($currentCrontab) . $newEntries;

            // Создаем временный файл
            $tempFile = tempnam(sys_get_temp_dir(), 'crontab_sweb');
            file_put_contents($tempFile, $fullCrontab);

            // Устанавливаем
            $result = shell_exec("crontab {$tempFile} 2>&1");
            unlink($tempFile);

            if ($result === null || empty($result)) {
                $this->info('✅ Crontab успешно установлен для SWEB!');
                
                // Показываем результат
                $this->newLine();
                $this->comment('📋 Текущий crontab:');
                $this->line(shell_exec('crontab -l'));
                
                // Инструкции по мониторингу
                $this->showMonitoringInstructions();
                
                return 0;
            } else {
                $this->error("❌ Ошибка установки crontab: {$result}");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Исключение: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Показать инструкции по мониторингу
     */
    private function showMonitoringInstructions(): void
    {
        $this->newLine();
        $this->info('🔍 Мониторинг и тестирование:');
        $this->newLine();
        
        $this->comment('Проверить логи работы:');
        $this->line('  tail -f logs/queue-worker.log');
        $this->newLine();
        
        $this->comment('Мониторинг очереди:');
        $this->line('  php8.1 artisan queue:monitor');
        $this->newLine();
        
        $this->comment('Тестирование системы:');
        $this->line('  php8.1 artisan lottery:test --quick --user-id=ВАШ_TELEGRAM_ID');
        $this->line('  php8.1 artisan lottery:test-winning-payout --user-id=ВАШ_TELEGRAM_ID --amount=5');
        $this->newLine();
        
        $this->comment('Проверка баланса:');
        $this->line('  php8.1 artisan stars:manage balance ВАШ_TELEGRAM_ID');
        $this->newLine();
        
        $this->comment('Ручной запуск queue worker для тестирования:');
        $this->line('  php8.1 artisan queue:work --stop-when-empty --max-time=10');
        $this->newLine();
        
        $this->info('🎯 Через 1-2 минуты система начнет автоматически обрабатывать задачи!');
    }
}
