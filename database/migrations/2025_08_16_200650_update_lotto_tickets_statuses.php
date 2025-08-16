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
        Schema::table('lotto_tickets', function (Blueprint $table) {
            // Обновляем enum для статусов
            $table->dropColumn('status');
        });
        
        Schema::table('lotto_tickets', function (Blueprint $table) {
            $table->enum('status', ['pending', 'paid', 'participating', 'completed', 'won', 'lost'])->default('pending')->after('payment_charge_id');
        });
        
        // Обновляем поле is_winner чтобы оно могло быть null
        Schema::table('lotto_tickets', function (Blueprint $table) {
            $table->boolean('is_winner')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lotto_tickets', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        
        Schema::table('lotto_tickets', function (Blueprint $table) {
            $table->enum('status', ['pending', 'paid', 'participating', 'won', 'lost'])->default('pending')->after('payment_charge_id');
        });
        
        Schema::table('lotto_tickets', function (Blueprint $table) {
            $table->boolean('is_winner')->default(false)->change();
        });
    }
};
