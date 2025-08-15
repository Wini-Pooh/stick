<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;
use App\Models\TelegramUserActivity;

class VerifyTelegramInitData
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $initData = $request->input('initData') ?? $request->header('X-Telegram-Init-Data');
        $botToken = env('TELEGRAM_BOT_TOKEN' ?? '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
        
        if (!$initData || !$botToken) {
            return response()->json(['error' => 'No initData or bot token'], 401);
        }
        
        if (!$this->validateInitData($initData, $botToken)) {
            return response()->json(['error' => 'Invalid Telegram initData signature'], 401);
        }
        
        // Парсим данные Telegram
        parse_str($initData, $data);
        
        // Создаем или обновляем пользователя в базе данных
        $telegramUser = TelegramUser::createOrUpdate($data);
        
        if ($telegramUser) {
            // Логируем активность
            $action = $this->getActionFromRequest($request);
            TelegramUserActivity::log(
                $telegramUser,
                $action,
                $request->path(),
                ['query_params' => $request->query()],
                $request
            );
            
            // Добавляем пользователя в request
            $request->merge([
                'telegram_user_data' => $data,
                'telegram_user' => $telegramUser
            ]);
        }
        
        return $next($request);
    }

    /**
     * Validate Telegram initData signature
     */
    private function validateInitData($initData, $botToken)
    {
        // Parse initData string to array
        parse_str($initData, $data);
        
        if (!isset($data['hash'])) {
            return false;
        }
        
        $hash = $data['hash'];
        unset($data['hash']);
        
        // Сортируем ключи
        ksort($data);
        
        // Создаем строку для проверки
        $dataCheckString = '';
        foreach ($data as $key => $value) {
            $dataCheckString .= "$key=$value\n";
        }
        $dataCheckString = rtrim($dataCheckString, "\n");
        
        // Создаем секретный ключ
        $secretKey = hash('sha256', $botToken, true);
        
        // Вычисляем хеш
        $calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));
        
        return hash_equals($calculatedHash, $hash);
    }

    /**
     * Определить тип действия на основе запроса
     */
    private function getActionFromRequest(Request $request)
    {
        $path = $request->path();
        
        if (str_contains($path, 'profile')) {
            return 'profile_view';
        } elseif (str_contains($path, 'debug')) {
            return 'debug_view';
        } elseif (str_contains($path, 'miniapp')) {
            return 'miniapp_access';
        }
        
        return 'api_call';
    }
}
