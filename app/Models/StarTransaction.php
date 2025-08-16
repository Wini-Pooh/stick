<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StarTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_user_id',
        'type',
        'amount',
        'reason',
        'transaction_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Связь с пользователем Telegram
     */
    public function telegramUser()
    {
        return $this->belongsTo(TelegramUser::class);
    }

    /**
     * Скоуп для транзакций определённого пользователя
     */
    public function scopeForUser($query, $telegramUserId)
    {
        return $query->where('telegram_user_id', $telegramUserId);
    }

    /**
     * Скоуп для транзакций определённого типа
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Скоуп для выигрышей
     */
    public function scopeWinnings($query)
    {
        return $query->where('type', 'lottery_win');
    }

    /**
     * Скоуп для покупок
     */
    public function scopePurchases($query)
    {
        return $query->where('type', 'lottery_purchase');
    }
}
