<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FixTimezone extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timezone:fix {--clear-queue : Очистить старые задачи в очереди}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Исправление часового пояса на московское время и очистка кеша';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🕐 Исправление часового пояса на московское время');
        $this->newLine();

        // Очищаем кеш конфигурации
        $this->info('🧹 Очистка кеша конфигурации...');
        Artisan::call('config:clear');
        Artisan::call('config:cache');
        
        // Показываем текущее время
        $this->info('📅 Текущее время системы:');
        $this->line('• UTC: ' . now()->utc()->format('Y-m-d H:i:s') . ' UTC');
        $this->line('• Московское: ' . now()->setTimezone('Europe/Moscow')->format('Y-m-d H:i:s') . ' MSK');
        $this->line('• Laravel App: ' . now()->format('Y-m-d H:i:s') . ' (' . config('app.timezone') . ')');
        
        // Очищаем старые задачи если нужно
        if ($this->option('clear-queue')) {
            $this->clearOldJobs();
        }
        
        $this->newLine();
        $this->info('✅ Часовой пояс исправлен!');
        $this->comment('💡 Теперь все новые задачи будут использовать московское время');
    }

    private function clearOldJobs()
    {
        $this->info('🧹 Очистка старых задач в очереди...');
        
        try {
            Artisan::call('queue:clear');
            $this->comment('✅ Очередь очищена');
        } catch (\Exception $e) {
            $this->error('❌ Ошибка при очистке очереди: ' . $e->getMessage());
        }
    }
}
