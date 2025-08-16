<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:monitor {--watch : Постоянное наблюдение} {--clear : Очистить заблокированные задачи}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Мониторинг состояния очереди и активных задач';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('watch')) {
            $this->watchQueue();
        } else {
            $this->showQueueStatus();
        }
    }

    private function showQueueStatus()
    {
        $this->info('📊 Состояние очереди jobs');
        $this->newLine();

        // Получаем статистику по jobs
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $this->comment("⏳ Задач в очереди: {$pendingJobs}");
        $this->comment("❌ Неудачных задач: {$failedJobs}");

        if ($pendingJobs > 0) {
            $this->newLine();
            $this->info('📋 Ближайшие задачи:');
            
            $jobs = DB::table('jobs')
                ->orderBy('available_at')
                ->limit(5)
                ->get();

            foreach ($jobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                $availableAt = date('H:i:s d.m.Y', $job->available_at);
                
                $this->line("• {$jobClass} - запланирована на {$availableAt}");
            }
        }

        if ($failedJobs > 0) {
            $this->newLine();
            $this->error('⚠️ Есть неудачные задачи! Проверьте логи.');
            $this->comment('Для повторного запуска: php artisan queue:retry all');
        }

        $this->newLine();
        $this->info('💡 Полезные команды:');
        $this->line('• php artisan queue:work - запустить обработку');
        $this->line('• php artisan queue:restart - перезапустить worker');
        $this->line('• php artisan queue:clear - очистить очередь');
        $this->line('• php artisan queue:failed - показать неудачные задачи');
    }

    private function watchQueue()
    {
        $this->info('👀 Наблюдение за очередью (Ctrl+C для остановки)');
        $this->newLine();

        while (true) {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            
            $timestamp = now()->format('H:i:s');
            $this->line("[{$timestamp}] Очередь: {$pendingJobs} | Неудачных: {$failedJobs}");
            
            if ($pendingJobs > 0) {
                $nextJob = DB::table('jobs')
                    ->orderBy('available_at')
                    ->first();
                
                if ($nextJob) {
                    $availableAt = date('H:i:s', $nextJob->available_at);
                    $this->comment("  Следующая задача в {$availableAt}");
                }
            }
            
            sleep(5);
        }
    }
}
