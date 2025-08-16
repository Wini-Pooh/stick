<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lotto_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lotto_game_id')->constrained('lotto_games')->onDelete('cascade');
            $table->date('draw_date'); // Дата розыгрыша
            $table->integer('total_tickets'); // Общее количество билетов
            $table->integer('total_pool'); // Общий банк в звёздах
            $table->integer('winners_count')->default(0); // Количество победителей
            $table->integer('total_winnings')->default(0); // Общая сумма выигрышей
            $table->enum('status', ['upcoming', 'in_progress', 'completed', 'cancelled'])->default('upcoming');
            $table->timestamp('executed_at')->nullable(); // Время проведения розыгрыша
            $table->json('draw_results')->nullable(); // Результаты розыгрыша
            $table->timestamps();

            // Индексы
            $table->index(['lotto_game_id', 'draw_date']);
            $table->index(['status']);
            $table->index(['draw_date']);
            $table->unique(['lotto_game_id', 'draw_date']); // Один розыгрыш в день для каждой игры
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotto_draws');
    }
};
