<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LottoDraw extends Model
{
    use HasFactory;

    protected $fillable = [
        'lotto_game_id',
        'draw_date',
        'total_tickets',
        'total_pool',
        'winners_count',
        'total_winnings',
        'status',
        'executed_at',
        'draw_results',
    ];

    protected $casts = [
        'draw_date' => 'date',
        'executed_at' => 'datetime',
        'draw_results' => 'array',
    ];

    /**
     * Связь с игрой
     */
    public function lottoGame()
    {
        return $this->belongsTo(LottoGame::class);
    }

    /**
     * Получить билеты этого розыгрыша
     */
    public function tickets()
    {
        return LottoTicket::where('lotto_game_id', $this->lotto_game_id)
            ->whereDate('purchased_at', $this->draw_date)
            ->where('status', 'participating');
    }

    /**
     * Скоуп для розыгрышей определённого статуса
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Скоуп для сегодняшних розыгрышей
     */
    public function scopeToday($query)
    {
        return $query->where('draw_date', today());
    }

    /**
     * Скоуп для завершённых розыгрышей
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Провести розыгрыш
     */
    public function conductDraw()
    {
        if ($this->status !== 'upcoming') {
            return false;
        }

        $this->update(['status' => 'in_progress']);

        $tickets = $this->tickets()->get();
        $game = $this->lottoGame;
        
        if ($tickets->isEmpty()) {
            $this->update([
                'status' => 'completed',
                'executed_at' => now(),
                'total_tickets' => 0,
                'total_pool' => 0,
            ]);
            return true;
        }

        $totalTickets = $tickets->count();
        $totalPool = $tickets->sum('stars_paid');
        $winnersCount = 0;
        $totalWinnings = 0;
        $winners = [];

        // Определяем количество победителей на основе шанса
        foreach ($tickets as $ticket) {
            $random = mt_rand(1, 10000) / 10000; // Генерируем случайное число от 0.0001 до 1.0000
            
            if ($random <= $game->win_chance) {
                $winnings = $ticket->stars_paid * $game->multiplier;
                $ticket->markAsWinner($winnings);
                
                $winnersCount++;
                $totalWinnings += $winnings;
                $winners[] = [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'user_id' => $ticket->telegram_user_id,
                    'winnings' => $winnings,
                ];
            } else {
                $ticket->markAsLoser();
            }
        }

        // Обновляем информацию о розыгрыше
        $this->update([
            'status' => 'completed',
            'executed_at' => now(),
            'total_tickets' => $totalTickets,
            'total_pool' => $totalPool,
            'winners_count' => $winnersCount,
            'total_winnings' => $totalWinnings,
            'draw_results' => [
                'winners' => $winners,
                'total_participants' => $totalTickets,
                'win_rate' => $totalTickets > 0 ? round(($winnersCount / $totalTickets) * 100, 2) : 0,
            ],
        ]);

        return true;
    }

    /**
     * Создать или получить розыгрыш на сегодня
     */
    public static function getOrCreateTodayDraw($gameId)
    {
        return self::firstOrCreate([
            'lotto_game_id' => $gameId,
            'draw_date' => today(),
        ], [
            'status' => 'upcoming',
            'total_tickets' => 0,
            'total_pool' => 0,
        ]);
    }
}
