<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupCrontab extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:setup-crontab 
                           {--path= : Путь к проекту (автоопределение если не указан)}
                           {--php= : Команда PHP (по умолчанию php8.1)}
                           {--user= : Пользователь для crontab (текущий если не указан)}
                           {--dry-run : Только показать команды без выполнения}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Настройка Crontab для автоматической обработки очереди Laravel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🕐 Настройка Crontab для очереди Laravel');
        $this->newLine();

        // Определяем параметры
        $projectPath = $this->option('path') ?: base_path();
        $phpCommand = $this->option('php') ?: 'php8.1';
        $user = $this->option('user') ?: get_current_user();
        $dryRun = $this->option('dry-run');

        // Нормализуем путь
        $projectPath = realpath($projectPath);
        
        $this->line("📁 Путь к проекту: {$projectPath}");
        $this->line("🐘 PHP команда: {$phpCommand}");
        $this->line("👤 Пользователь: {$user}");
        $this->newLine();

        // Проверяем доступность команд
        if (!$this->checkRequirements($projectPath, $phpCommand)) {
            return 1;
        }

        // Генерируем crontab записи
        $crontabEntries = $this->generateCrontabEntries($projectPath, $phpCommand);

        // Показываем что будет добавлено
        $this->showCrontabEntries($crontabEntries);

        if ($dryRun) {
            $this->info('🔍 Режим проверки (--dry-run) - команды не выполнены');
            $this->showInstallInstructions($crontabEntries);
            return 0;
        }

        // Подтверждение
        if (!$this->confirm('Добавить эти записи в crontab?')) {
            $this->info('Операция отменена');
            return 0;
        }

        // Установка crontab
        return $this->installCrontab($crontabEntries, $user);
    }

    /**
     * Проверка требований
     */
    private function checkRequirements($projectPath, $phpCommand): bool
    {
        $this->info('🔍 Проверка требований...');

        // Проверяем путь к проекту
        if (!is_dir($projectPath)) {
            $this->error("❌ Путь к проекту не найден: {$projectPath}");
            return false;
        }

        // Проверяем artisan
        $artisanPath = $projectPath . '/artisan';
        if (!file_exists($artisanPath)) {
            $this->error("❌ Файл artisan не найден: {$artisanPath}");
            return false;
        }

        // Проверяем PHP
        $phpCheck = shell_exec("which {$phpCommand} 2>/dev/null");
        if (empty($phpCheck)) {
            $this->warn("⚠️ PHP команда '{$phpCommand}' не найдена в PATH");
            $this->comment("💡 Попробуйте указать полный путь: --php=/usr/bin/php8.1");
            
            if (!$this->confirm('Продолжить с текущей командой PHP?')) {
                return false;
            }
        } else {
            $this->comment("✅ PHP найден: " . trim($phpCheck));
        }

        // Проверяем crontab
        $crontabCheck = shell_exec('which crontab 2>/dev/null');
        if (empty($crontabCheck)) {
            $this->error("❌ Команда crontab не найдена");
            $this->comment("💡 Установите cron: apt-get install cron (Ubuntu) или yum install cronie (CentOS)");
            return false;
        }

        $this->comment("✅ Все требования выполнены");
        return true;
    }

    /**
     * Генерация записей crontab
     */
    private function generateCrontabEntries($projectPath, $phpCommand): array
    {
        $logPath = $projectPath . '/storage/logs';
        
        return [
            'queue_worker' => [
                'schedule' => '* * * * *',
                'command' => "cd {$projectPath} && {$phpCommand} artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3",
                'output' => ">> {$logPath}/queue-worker.log 2>&1",
                'description' => 'Laravel Queue Worker - каждую минуту'
            ],
            'fix_delayed' => [
                'schedule' => '*/5 * * * *',
                'command' => "cd {$projectPath} && {$phpCommand} artisan queue:fix-delayed --force",
                'output' => ">> {$logPath}/queue-fix.log 2>&1",
                'description' => 'Исправление зависших задач - каждые 5 минут'
            ],
            'queue_monitor' => [
                'schedule' => '*/30 * * * *',
                'command' => "cd {$projectPath} && {$phpCommand} artisan queue:monitor",
                'output' => ">> {$logPath}/queue-monitor.log 2>&1",
                'description' => 'Мониторинг очереди - каждые 30 минут'
            ],
            'log_cleanup' => [
                'schedule' => '0 2 * * *',
                'command' => "find {$logPath}/*.log -type f -mtime +7 -delete",
                'output' => '2>/dev/null',
                'description' => 'Очистка старых логов - ежедневно в 2:00'
            ]
        ];
    }

    /**
     * Показать записи crontab
     */
    private function showCrontabEntries($entries): void
    {
        $this->info('📋 Будут добавлены следующие записи в crontab:');
        $this->newLine();

        foreach ($entries as $key => $entry) {
            $this->comment("# {$entry['description']}");
            $fullCommand = "{$entry['schedule']} {$entry['command']} {$entry['output']}";
            $this->line($fullCommand);
            $this->newLine();
        }
    }

    /**
     * Показать инструкции для ручной установки
     */
    private function showInstallInstructions($entries): void
    {
        $this->newLine();
        $this->info('📝 Для ручной установки выполните:');
        $this->newLine();
        
        $this->comment('1. Откройте редактор crontab:');
        $this->line('   crontab -e');
        $this->newLine();
        
        $this->comment('2. Добавьте следующие строки:');
        foreach ($entries as $entry) {
            $this->line("   # {$entry['description']}");
            $this->line("   {$entry['schedule']} {$entry['command']} {$entry['output']}");
            $this->newLine();
        }
        
        $this->comment('3. Сохраните и выйдите');
        $this->comment('4. Проверьте: crontab -l');
    }

    /**
     * Установка crontab
     */
    private function installCrontab($entries, $user): int
    {
        $this->info('🔧 Установка crontab...');

        try {
            // Получаем текущий crontab
            $currentCrontab = shell_exec('crontab -l 2>/dev/null') ?: '';
            
            // Добавляем маркеры для наших записей
            $newEntries = "\n# Laravel Queue Worker - Auto Generated\n";
            foreach ($entries as $entry) {
                $newEntries .= "# {$entry['description']}\n";
                $newEntries .= "{$entry['schedule']} {$entry['command']} {$entry['output']}\n";
            }
            $newEntries .= "# End Laravel Queue Worker\n";

            // Проверяем, не добавлены ли уже наши записи
            if (strpos($currentCrontab, '# Laravel Queue Worker - Auto Generated') !== false) {
                $this->warn('⚠️ Записи Laravel Queue Worker уже существуют в crontab');
                
                if ($this->confirm('Обновить существующие записи?')) {
                    // Удаляем старые записи
                    $pattern = '/# Laravel Queue Worker - Auto Generated.*?# End Laravel Queue Worker\n/s';
                    $currentCrontab = preg_replace($pattern, '', $currentCrontab);
                } else {
                    $this->info('Операция отменена');
                    return 0;
                }
            }

            // Объединяем старый и новый crontab
            $fullCrontab = $currentCrontab . $newEntries;

            // Создаем временный файл
            $tempFile = tempnam(sys_get_temp_dir(), 'crontab');
            file_put_contents($tempFile, $fullCrontab);

            // Устанавливаем новый crontab
            $result = shell_exec("crontab {$tempFile} 2>&1");
            unlink($tempFile);

            if ($result === null || empty($result)) {
                $this->info('✅ Crontab успешно установлен!');
                
                // Показываем итоговый crontab
                $this->newLine();
                $this->comment('📋 Текущий crontab:');
                $this->line(shell_exec('crontab -l'));
                
                // Создаем директории для логов
                $this->createLogDirectories();
                
                // Показываем команды для мониторинга
                $this->showMonitoringCommands();
                
                return 0;
            } else {
                $this->error("❌ Ошибка установки crontab: {$result}");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Исключение при установке crontab: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Создание директорий для логов
     */
    private function createLogDirectories(): void
    {
        $logPath = base_path('storage/logs');
        
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
            $this->comment("📁 Создана директория для логов: {$logPath}");
        }

        // Создаем файлы логов с правильными правами
        $logFiles = ['queue-worker.log', 'queue-fix.log', 'queue-monitor.log'];
        foreach ($logFiles as $logFile) {
            $fullPath = $logPath . '/' . $logFile;
            if (!file_exists($fullPath)) {
                touch($fullPath);
                chmod($fullPath, 0664);
            }
        }
    }

    /**
     * Показать команды для мониторинга
     */
    private function showMonitoringCommands(): void
    {
        $this->newLine();
        $this->info('🔍 Команды для мониторинга:');
        $this->newLine();
        
        $logPath = base_path('storage/logs');
        
        $this->comment('Проверить работу crontab:');
        $this->line('  crontab -l');
        $this->newLine();
        
        $this->comment('Мониторинг логов queue worker:');
        $this->line("  tail -f {$logPath}/queue-worker.log");
        $this->newLine();
        
        $this->comment('Мониторинг исправления задач:');
        $this->line("  tail -f {$logPath}/queue-fix.log");
        $this->newLine();
        
        $this->comment('Проверить состояние очереди:');
        $this->line('  php artisan queue:monitor');
        $this->newLine();
        
        $this->comment('Создать тестовую задачу:');
        $this->line('  php artisan queue:test-timing --seconds=30');
        $this->newLine();
        
        $this->comment('Системные логи cron:');
        $this->line('  # Ubuntu/Debian: tail -f /var/log/syslog | grep CRON');
        $this->line('  # CentOS/RHEL: tail -f /var/log/cron');
    }
}
