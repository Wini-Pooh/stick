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
        Schema::create('lotto_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained('telegram_users')->onDelete('cascade');
            $table->foreignId('lotto_game_id')->constrained('lotto_games')->onDelete('cascade');
            $table->string('ticket_number', 20)->unique(); // Уникальный номер билета
            $table->integer('stars_paid'); // Количество потраченных звёзд
            $table->string('payment_charge_id')->nullable(); // ID платежа в Telegram
            $table->enum('status', ['pending', 'paid', 'participating', 'won', 'lost'])->default('pending');
            $table->timestamp('purchased_at')->nullable(); // Время покупки
            $table->timestamp('drawn_at')->nullable(); // Время розыгрыша
            $table->boolean('is_winner')->default(false);
            $table->integer('winnings')->default(0); // Размер выигрыша в звёздах
            $table->json('payment_data')->nullable(); // Данные платежа от Telegram
            $table->timestamps();

            // Индексы
            $table->index(['telegram_user_id', 'lotto_game_id']);
            $table->index(['status']);
            $table->index(['purchased_at']);
            $table->index(['is_winner']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotto_tickets');
    }
};
