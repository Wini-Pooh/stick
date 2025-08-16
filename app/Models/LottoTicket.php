<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LottoTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_user_id',
        'lotto_game_id',
        'ticket_number',
        'stars_paid',
        'payment_charge_id',
        'status',
        'purchased_at',
        'drawn_at',
        'is_winner',
        'winnings',
        'payment_data',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'drawn_at' => 'datetime',
        'is_winner' => 'boolean',
        'payment_data' => 'array',
    ];

    /**
     * Связь с пользователем Telegram
     */
    public function telegramUser()
    {
        return $this->belongsTo(TelegramUser::class);
    }

    /**
     * Связь с игрой
     */
    public function lottoGame()
    {
        return $this->belongsTo(LottoGame::class);
    }

    /**
     * Генерировать уникальный номер билета
     */
    public static function generateTicketNumber()
    {
        do {
            $number = 'LT' . date('Ymd') . strtoupper(Str::random(6));
        } while (self::where('ticket_number', $number)->exists());

        return $number;
    }

    /**
     * Скоуп для билетов определённого пользователя
     */
    public function scopeForUser($query, $telegramUserId)
    {
        return $query->where('telegram_user_id', $telegramUserId);
    }

    /**
     * Скоуп для билетов определённой игры
     */
    public function scopeForGame($query, $gameId)
    {
        return $query->where('lotto_game_id', $gameId);
    }

    /**
     * Скоуп для билетов определённого статуса
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Скоуп для билетов за сегодня
     */
    public function scopeToday($query)
    {
        return $query->whereDate('purchased_at', today());
    }

    /**
     * Скоуп для выигрышных билетов
     */
    public function scopeWinners($query)
    {
        return $query->where('is_winner', true);
    }

    /**
     * Отметить билет как выигрышный
     */
    public function markAsWinner($winnings)
    {
        $this->update([
            'is_winner' => true,
            'winnings' => $winnings,
            'status' => 'won',
            'drawn_at' => now(),
        ]);
    }

    /**
     * Отметить билет как проигрышный
     */
    public function markAsLoser()
    {
        $this->update([
            'is_winner' => false,
            'status' => 'lost',
            'drawn_at' => now(),
        ]);
    }
}
