<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramUserActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_user_id',
        'action',
        'endpoint',
        'data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Связь с пользователем Telegram
     */
    public function telegramUser()
    {
        return $this->belongsTo(TelegramUser::class);
    }

    /**
     * Логировать активность пользователя
     */
    public static function log($telegramUser, $action, $endpoint = null, $data = null, $request = null)
    {
        return self::create([
            'telegram_user_id' => $telegramUser->id,
            'action' => $action,
            'endpoint' => $endpoint,
            'data' => $data,
            'ip_address' => $request ? $request->ip() : null,
            'user_agent' => $request ? $request->header('User-Agent') : null,
        ]);
    }

    /**
     * Скоуп для определенного действия
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Скоуп для определенного пользователя
     */
    public function scopeForUser($query, $telegramUserId)
    {
        return $query->where('telegram_user_id', $telegramUserId);
    }

    /**
     * Скоуп для последних записей
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
