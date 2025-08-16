<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessLotteryResult;
use Illuminate\Support\Facades\DB;

class TestQueueTiming extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:test-timing {--seconds=30 : Задержка в секундах}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирование работы отложенных задач в очереди';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $delaySeconds = (int) $this->option('seconds');
        
        $this->info("🧪 Тестирование отложенных задач (задержка: {$delaySeconds} сек)");
        $this->newLine();

        // Показываем текущее время
        $currentTime = now();
        $executeTime = $currentTime->copy()->addSeconds($delaySeconds);
        
        $this->comment('📅 Текущее время: ' . $currentTime->format('H:i:s d.m.Y') . ' MSK');
        $this->comment('⏰ Время выполнения: ' . $executeTime->format('H:i:s d.m.Y') . ' MSK');
        $this->newLine();

        // Создаем тестовую задачу
        $testJobId = $this->createTestJob($delaySeconds);
        
        if (!$testJobId) {
            $this->error('❌ Не удалось создать тестовую задачу');
            return;
        }

        $this->info("✅ Тестовая задача создана (ID: {$testJobId})");
        $this->comment("⏳ Ожидание выполнения через {$delaySeconds} секунд...");
        $this->newLine();

        // Мониторим выполнение
        $this->monitorJobExecution($testJobId, $delaySeconds + 10);
    }

    private function createTestJob($delaySeconds)
    {
        try {
            // Создаем фиктивную задачу ProcessLotteryResult с тестовыми данными
            $job = ProcessLotteryResult::dispatch(999999, 999999999)
                ->delay(now()->addSeconds($delaySeconds));
                
            // Получаем ID созданной задачи из базы
            $latestJob = DB::table('jobs')
                ->orderBy('id', 'desc')
                ->first();
                
            return $latestJob ? $latestJob->id : null;
            
        } catch (\Exception $e) {
            $this->error('Ошибка создания задачи: ' . $e->getMessage());
            return null;
        }
    }

    private function monitorJobExecution($jobId, $timeoutSeconds)
    {
        $startTime = time();
        $executed = false;
        
        while ((time() - $startTime) < $timeoutSeconds) {
            // Проверяем, существует ли задача в очереди
            $jobExists = DB::table('jobs')->where('id', $jobId)->exists();
            
            if (!$jobExists) {
                $this->info('✅ Задача выполнена успешно!');
                $executionTime = time() - $startTime;
                $this->comment("⏱️ Время выполнения: {$executionTime} секунд");
                $executed = true;
                break;
            }
            
            // Показываем прогресс каждые 5 секунд
            $elapsed = time() - $startTime;
            if ($elapsed % 5 == 0) {
                $this->comment("⏳ Прошло {$elapsed} сек... Задача еще в очереди");
            }
            
            sleep(1);
        }
        
        if (!$executed) {
            $this->error('❌ Задача не была выполнена в течение таймаута!');
            $this->comment('🔧 Возможные проблемы:');
            $this->line('• Worker не запущен');
            $this->line('• Worker работает без параметра --sleep');
            $this->line('• Проблемы с часовым поясом');
            $this->newLine();
            $this->comment('💡 Решение:');
            $this->line('php8.1 artisan queue:work --sleep=3 --tries=3');
        }
    }
}
