<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckCronStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:check-cron-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверка работы cron задач и логов очереди';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Проверка статуса cron задач и очереди');
        $this->newLine();

        // Проверяем логи
        $this->checkLogs();

        // Проверяем состояние очереди
        $this->checkQueueStatus();

        // Проверяем последнее выполнение
        $this->checkLastExecution();

        // Показываем инструкции
        $this->showInstructions();
    }

    /**
     * Проверка логов
     */
    private function checkLogs(): void
    {
        $this->info('📋 Проверка логов:');
        $this->newLine();

        $logFiles = [
            'logs/queue-worker.log' => 'Queue Worker',
            'logs/queue-fix.log' => 'Fix Delayed Jobs',
            'logs/queue-monitor.log' => 'Queue Monitor',
            'storage/logs/laravel.log' => 'Laravel Application'
        ];

        foreach ($logFiles as $file => $description) {
            if (file_exists($file)) {
                $size = filesize($file);
                $modified = date('Y-m-d H:i:s', filemtime($file));
                
                if ($size > 0) {
                    $this->comment("✅ {$description}: {$this->formatBytes($size)}, изменен {$modified}");
                    
                    // Показываем последние строки для queue-worker.log
                    if ($file === 'logs/queue-worker.log') {
                        $lastLines = $this->getLastLines($file, 3);
                        if (!empty($lastLines)) {
                            $this->line('   Последние записи:');
                            foreach ($lastLines as $line) {
                                $this->line('   ' . trim($line));
                            }
                        }
                    }
                } else {
                    $this->warn("⚠️ {$description}: файл пустой, создан {$modified}");
                }
            } else {
                $this->error("❌ {$description}: файл не найден ({$file})");
            }
        }
        $this->newLine();
    }

    /**
     * Проверка состояния очереди
     */
    private function checkQueueStatus(): void
    {
        $this->info('⚙️ Состояние очереди:');
        $this->newLine();

        try {
            // Проверяем количество задач в очереди
            $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
            $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();

            $this->comment("📊 Задач в очереди: {$pendingJobs}");
            $this->comment("❌ Неудачных задач: {$failedJobs}");

            if ($pendingJobs > 0) {
                $this->warn("⚠️ Есть необработанные задачи! Проверьте работу cron.");
            }

            if ($failedJobs > 0) {
                $this->warn("⚠️ Есть неудачные задачи! Проверьте логи.");
                
                // Показываем последние неудачные задачи
                $recentFailed = \Illuminate\Support\Facades\DB::table('failed_jobs')
                    ->orderBy('failed_at', 'desc')
                    ->limit(3)
                    ->get(['payload', 'exception', 'failed_at']);

                foreach ($recentFailed as $failed) {
                    $payload = json_decode($failed->payload, true);
                    $jobClass = $payload['displayName'] ?? 'Unknown Job';
                    $this->line("   - {$jobClass} ({$failed->failed_at})");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Ошибка проверки очереди: " . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * Проверка последнего выполнения
     */
    private function checkLastExecution(): void
    {
        $this->info('⏰ Последнее выполнение:');
        $this->newLine();

        // Проверяем логи на предмет недавней активности
        $logFile = 'logs/queue-worker.log';
        if (file_exists($logFile)) {
            $lastModified = filemtime($logFile);
            $timeDiff = time() - $lastModified;

            if ($timeDiff < 120) { // менее 2 минут назад
                $this->comment("✅ Queue worker активен (последняя активность: " . $this->timeAgo($timeDiff) . ")");
            } elseif ($timeDiff < 300) { // менее 5 минут назад
                $this->warn("⚠️ Queue worker неактивен уже " . $this->timeAgo($timeDiff));
            } else {
                $this->error("❌ Queue worker не работает уже " . $this->timeAgo($timeDiff));
                $this->comment("💡 Проверьте настройки cron в панели управления хостингом");
            }
        } else {
            $this->error("❌ Лог файл queue worker не найден");
            $this->comment("💡 Cron задачи скорее всего не настроены");
        }

        $this->newLine();
    }

    /**
     * Показать инструкции
     */
    private function showInstructions(): void
    {
        $this->info('💡 Полезные команды:');
        $this->newLine();

        $this->comment('Ручной запуск queue worker (для тестирования):');
        $this->line('  php8.1 artisan queue:work --stop-when-empty --max-time=60');
        $this->newLine();

        $this->comment('Посмотреть логи в реальном времени:');
        $this->line('  tail -f logs/queue-worker.log');
        $this->newLine();

        $this->comment('Создать тестовую задачу:');
        $this->line('  php8.1 artisan queue:test-timing --seconds=30');
        $this->newLine();

        $this->comment('Тест лотереи:');
        $this->line('  php8.1 artisan lottery:test --quick --user-id=ВАШ_TELEGRAM_ID');
        $this->newLine();

        $this->comment('Проверить cron в панели управления хостингом:');
        $this->line('  Убедитесь что добавлены задачи с расписанием * * * * * и */5 * * * *');
        $this->newLine();

        if (!$this->isCronWorking()) {
            $this->warn('⚠️ Похоже что cron задачи не работают!');
            $this->comment('Проверьте настройки в панели управления хостингом SWEB');
        }
    }

    /**
     * Проверка работает ли cron
     */
    private function isCronWorking(): bool
    {
        $logFile = 'logs/queue-worker.log';
        if (!file_exists($logFile)) {
            return false;
        }

        $lastModified = filemtime($logFile);
        $timeDiff = time() - $lastModified;

        return $timeDiff < 300; // активность менее 5 минут назад
    }

    /**
     * Получить последние строки файла
     */
    private function getLastLines($file, $lines = 10): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $content = file($file);
        return array_slice($content, -$lines);
    }

    /**
     * Форматировать размер файла
     */
    private function formatBytes($size): string
    {
        if ($size < 1024) return $size . ' B';
        if ($size < 1048576) return round($size / 1024, 1) . ' KB';
        return round($size / 1048576, 1) . ' MB';
    }

    /**
     * Время назад в читаемом формате
     */
    private function timeAgo($seconds): string
    {
        if ($seconds < 60) return $seconds . ' сек назад';
        if ($seconds < 3600) return round($seconds / 60) . ' мин назад';
        return round($seconds / 3600) . ' час назад';
    }
}
