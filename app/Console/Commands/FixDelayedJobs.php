<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class FixDelayedJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:fix-delayed {--force : Принудительно выполнить просроченные задачи}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Исправление проблем с отложенными задачами в очереди';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Исправление отложенных задач в очереди');
        $this->newLine();

        $this->checkDelayedJobs();

        if ($this->option('force')) {
            $this->forceDelayedJobs();
        }

        $this->showWorkerCommands();
    }

    private function checkDelayedJobs()
    {
        $this->info('📊 Анализ состояния очереди...');
        
        $now = now()->timestamp;
        
        // Просроченные задачи (should be processed now)
        $overdueJobs = DB::table('jobs')
            ->where('available_at', '<=', $now)
            ->count();
            
        // Будущие задачи
        $futureJobs = DB::table('jobs')
            ->where('available_at', '>', $now)
            ->count();
            
        $this->comment("⏰ Задач, готовых к выполнению: {$overdueJobs}");
        $this->comment("🔮 Задач в будущем: {$futureJobs}");
        
        if ($overdueJobs > 0) {
            $this->newLine();
            $this->warn("⚠️ Есть {$overdueJobs} просроченных задач!");
            $this->comment('Возможные причины:');
            $this->line('• Worker не запущен');
            $this->line('• Worker запущен без параметра --sleep');
            $this->line('• Worker нужно перезапустить');
            $this->newLine();
            $this->comment('🔧 Решение: Перезапустите worker с правильными параметрами');
        }
        
        // Показываем детали просроченных задач
        if ($overdueJobs > 0) {
            $this->showOverdueJobs();
        }
    }

    private function showOverdueJobs()
    {
        $this->info('📋 Просроченные задачи:');
        
        $jobs = DB::table('jobs')
            ->where('available_at', '<=', now()->timestamp)
            ->orderBy('available_at')
            ->limit(5)
            ->get();

        foreach ($jobs as $job) {
            $payload = json_decode($job->payload, true);
            $jobClass = $payload['displayName'] ?? 'Unknown';
            
            $scheduledTime = \Carbon\Carbon::createFromTimestamp($job->available_at)
                ->setTimezone('Europe/Moscow')
                ->format('H:i:s d.m.Y');
                
            $delay = \Carbon\Carbon::createFromTimestamp($job->available_at)
                ->diffForHumans(now(), true);
                
            $this->line("• {$jobClass} - должна была выполниться в {$scheduledTime} MSK (просрочена на {$delay})");
        }
    }

    private function forceDelayedJobs()
    {
        $this->newLine();
        $this->info('🚀 Принудительное выполнение просроченных задач...');
        
        $jobs = DB::table('jobs')
            ->where('available_at', '<=', now()->timestamp)
            ->get();
            
        if ($jobs->isEmpty()) {
            $this->comment('✅ Нет просроченных задач для выполнения');
            return;
        }
        
        $this->comment("Найдено {$jobs->count()} просроченных задач");
        
        // Устанавливаем available_at в текущее время для немедленного выполнения
        $updated = DB::table('jobs')
            ->where('available_at', '<=', now()->timestamp)
            ->update(['available_at' => now()->timestamp]);
            
        $this->comment("✅ Обновлено {$updated} задач для немедленного выполнения");
        $this->comment('💡 Запустите worker для обработки: php8.1 artisan queue:work');
    }

    private function showWorkerCommands()
    {
        $this->newLine();
        $this->info('🔧 Правильные команды для запуска worker:');
        $this->newLine();
        
        $this->comment('Для хостинга (рекомендуется):');
        $this->line('php8.1 artisan queue:work --sleep=3 --tries=3 --max-time=3600');
        $this->newLine();
        
        $this->comment('Для разработки:');
        $this->line('php artisan queue:work --sleep=1 --tries=3');
        $this->newLine();
        
        $this->comment('Перезапуск worker (если изменили код):');
        $this->line('php8.1 artisan queue:restart');
        $this->newLine();
        
        $this->info('📋 Параметры:');
        $this->line('• --sleep=3    - проверка новых задач каждые 3 сек');
        $this->line('• --tries=3    - максимум 3 попытки выполнения');
        $this->line('• --max-time   - перезапуск worker через час');
        $this->line('• --timeout    - таймаут на выполнение одной задачи');
    }
}
