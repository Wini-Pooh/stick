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
        $botToken = env('TELEGRAM_BOT_TOKEN', '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
        
        Log::info('VerifyTelegramInitData middleware', [
            'initData_present' => !empty($initData),
            'botToken_present' => !empty($botToken),
            'request_method' => $request->method(),
            'request_path' => $request->path(),
            'headers' => $request->headers->all()
        ]);
        
        if (!$initData || !$botToken) {
            Log::error('Missing initData or bot token', [
                'initData' => $initData ? 'present' : 'missing',
                'botToken' => $botToken ? 'present' : 'missing'
            ]);
            return response()->json(['error' => 'No initData or bot token'], 401);
        }
        
        if (!$this->validateInitData($initData, $botToken)) {
            Log::error('Invalid Telegram initData signature', [
                'initData' => substr($initData, 0, 100) . '...'
            ]);
            return response()->json(['error' => 'Invalid Telegram initData signature'], 401);
        }
        
        Log::info('Telegram initData validation successful');
        
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
        try {
            // Parse initData string to array
            parse_str($initData, $data);
            
            Log::info('Validating initData', [
                'data_keys' => array_keys($data),
                'has_hash' => isset($data['hash'])
            ]);
            
            if (!isset($data['hash'])) {
                Log::error('No hash in initData');
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
            
            Log::info('Hash validation', [
                'expected_hash' => $hash,
                'calculated_hash' => $calculatedHash,
                'data_check_string' => substr($dataCheckString, 0, 200)
            ]);
            
            return hash_equals($calculatedHash, $hash);
        } catch (\Exception $e) {
            Log::error('Error validating initData', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
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
