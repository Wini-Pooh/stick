<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TelegramUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'first_name',
        'last_name',
        'username',
        'language_code',
        'is_bot',
        'is_premium',
        'allows_write_to_pm',
        'photo_url',
        'raw_data',
        'first_seen_at',
        'last_seen_at',
        'visits_count',
        'stars_balance',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'is_bot' => 'boolean',
        'is_premium' => 'boolean',
        'allows_write_to_pm' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Создать или обновить пользователя Telegram
     */
    public static function createOrUpdate(array $telegramData)
    {
        $user = json_decode($telegramData['user'] ?? '{}', true);
        
        if (!$user || !isset($user['id'])) {
            return null;
        }

        $telegramUser = self::where('telegram_id', $user['id'])->first();

        $userData = [
            'telegram_id' => $user['id'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? null,
            'username' => $user['username'] ?? null,
            'language_code' => $user['language_code'] ?? 'en',
            'is_bot' => $user['is_bot'] ?? false,
            'is_premium' => $user['is_premium'] ?? false,
            'allows_write_to_pm' => $user['allows_write_to_pm'] ?? false,
            'photo_url' => $user['photo_url'] ?? null,
            'raw_data' => $telegramData,
            'last_seen_at' => now(),
        ];

        if ($telegramUser) {
            // Обновляем существующего пользователя
            $userData['visits_count'] = $telegramUser->visits_count + 1;
            $telegramUser->update($userData);
            return $telegramUser;
        } else {
            // Создаем нового пользователя
            $userData['first_seen_at'] = now();
            $userData['visits_count'] = 1;
            return self::create($userData);
        }
    }

    /**
     * Получить полное имя пользователя
     */
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Получить отображаемое имя (имя или username)
     */
    public function getDisplayNameAttribute()
    {
        if ($this->full_name) {
            return $this->full_name;
        }
        
        return $this->username ? '@' . $this->username : 'User #' . $this->telegram_id;
    }

    /**
     * Проверить, онлайн ли пользователь (активность за последние 5 минут)
     */
    public function getIsOnlineAttribute()
    {
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) <= 5;
    }

    /**
     * Получить время последней активности в читаемом формате
     */
    public function getLastSeenHumanAttribute()
    {
        return $this->last_seen_at ? $this->last_seen_at->diffForHumans() : 'Никогда';
    }

    /**
     * Скоуп для поиска по Telegram ID
     */
    public function scopeByTelegramId($query, $telegramId)
    {
        return $query->where('telegram_id', $telegramId);
    }

    /**
     * Скоуп для активных пользователей (активность за последний час)
     */
    public function scopeActive($query)
    {
        return $query->where('last_seen_at', '>=', now()->subHour());
    }

    /**
     * Скоуп для недавних пользователей (зарегистрированы за последнюю неделю)
     */
    public function scopeRecent($query)
    {
        return $query->where('first_seen_at', '>=', now()->subWeek());
    }

    /**
     * Связь с активностями пользователя
     */
    public function activities()
    {
        return $this->hasMany(TelegramUserActivity::class);
    }

    /**
     * Получить последние активности
     */
    public function recentActivities($limit = 10)
    {
        return $this->activities()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
