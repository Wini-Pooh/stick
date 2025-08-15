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
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->unique(); // ID пользователя в Telegram
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('language_code', 10)->default('en');
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_premium')->default(false);
            $table->boolean('allows_write_to_pm')->default(false);
            $table->string('photo_url')->nullable();
            $table->json('raw_data')->nullable(); // Полные данные от Telegram
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->integer('visits_count')->default(1);
            $table->timestamps();
            
            // Индексы для быстрого поиска
            $table->index(['telegram_id']);
            $table->index(['username']);
            $table->index(['last_seen_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
