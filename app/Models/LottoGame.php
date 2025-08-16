<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LottoGame extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'multiplier',
        'ticket_price',
        'win_chance',
        'is_active',
        'description',
        'color',
    ];

    protected $casts = [
        'win_chance' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /**
     * Связь с билетами
     */
    public function tickets()
    {
        return $this->hasMany(LottoTicket::class);
    }

    /**
     * Связь с розыгрышами
     */
    public function draws()
    {
        return $this->hasMany(LottoDraw::class);
    }

    /**
     * Скоуп для активных игр
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Получить количество билетов для сегодняшнего розыгрыша
     */
    public function getTodayTicketsCount()
    {
        return $this->tickets()
            ->whereDate('purchased_at', today())
            ->where('status', 'participating')
            ->count();
    }

    /**
     * Получить общий банк для сегодняшнего розыгрыша
     */
    public function getTodayPool()
    {
        return $this->tickets()
            ->whereDate('purchased_at', today())
            ->where('status', 'participating')
            ->sum('stars_paid');
    }

    /**
     * Получить потенциальный выигрыш
     */
    public function getPotentialWinnings()
    {
        return $this->ticket_price * $this->multiplier;
    }
}
