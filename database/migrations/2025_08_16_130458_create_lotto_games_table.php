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
        Schema::create('lotto_games', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название игры (например, "x2", "x3", "x10")
            $table->integer('multiplier'); // Множитель выигрыша (2, 3, 10)
            $table->integer('ticket_price'); // Цена билета в звёздах
            $table->decimal('win_chance', 5, 4); // Шанс выигрыша (от 0.0001 до 1.0000)
            $table->boolean('is_active')->default(true); // Активна ли игра
            $table->text('description')->nullable(); // Описание игры
            $table->string('color', 7)->default('#007bff'); // Цвет для UI
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotto_games');
    }
};
