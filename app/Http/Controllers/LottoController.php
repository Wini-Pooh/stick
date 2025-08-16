<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\LottoGame;
use App\Models\LottoTicket;
use App\Models\LottoDraw;
use App\Models\TelegramUser;
use App\Models\TelegramUserActivity;

class LottoController extends Controller
{
    private $botToken;
    private $botUrl;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN', '8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4');
        $this->botUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Получить список активных игр
     */
    public function getGames(Request $request)
    {
        $games = LottoGame::active()->get();
        
        // Добавляем информацию о сегодняшних розыгрышах
        $games = $games->map(function ($game) {
            $todayTickets = $game->getTodayTicketsCount();
            $todayPool = $game->getTodayPool();
            
            return [
                'id' => $game->id,
                'name' => $game->name,
                'multiplier' => $game->multiplier,
                'ticket_price' => $game->ticket_price,
                'win_chance' => $game->win_chance,
                'description' => $game->description,
                'color' => $game->color,
                'potential_winnings' => $game->getPotentialWinnings(),
                'today_tickets' => $todayTickets,
                'today_pool' => $todayPool,
            ];
        });

        return response()->json([
            'success' => true,
            'games' => $games,
        ]);
    }

    /**
     * Купить билет
     */
    public function buyTicket(Request $request)
    {
        $request->validate([
            'game_id' => 'required|exists:lotto_games,id',
            'initData' => 'required|string',
        ]);

        // Получаем данные пользователя из initData
        $telegramUser = $this->getTelegramUserFromInitData($request->input('initData'));
        if (!$telegramUser) {
            return response()->json([
                'success' => false,
                'error' => 'Невалидные данные пользователя',
            ], 400);
        }

        $game = LottoGame::findOrFail($request->input('game_id'));
        
        if (!$game->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Игра неактивна',
            ], 400);
        }

        // Создаём билет
        $ticket = LottoTicket::create([
            'telegram_user_id' => $telegramUser->id,
            'lotto_game_id' => $game->id,
            'ticket_number' => LottoTicket::generateTicketNumber(),
            'stars_paid' => $game->ticket_price,
            'status' => 'pending',
        ]);

        // Создаём инвойс для оплаты звёздами
        $invoiceSent = $this->createStarInvoice($telegramUser, $ticket, $game);

        if (!$invoiceSent) {
            $ticket->delete();
            return response()->json([
                'success' => false,
                'error' => 'Ошибка отправки счёта на оплату',
            ], 500);
        }

        // Логируем активность
        TelegramUserActivity::log(
            $telegramUser,
            'lotto_ticket_created',
            'buyTicket',
            [
                'game_id' => $game->id,
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'price' => $game->ticket_price,
            ],
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Счёт на оплату отправлен в чат с ботом. Проверьте Telegram для завершения покупки.',
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'game_name' => $game->name,
                'price' => $game->ticket_price,
                'potential_winnings' => $game->getPotentialWinnings(),
            ],
        ]);
    }

    /**
     * Получить билеты пользователя
     */
    public function getUserTickets(Request $request)
    {
        $request->validate([
            'initData' => 'required|string',
        ]);

        $telegramUser = $this->getTelegramUserFromInitData($request->input('initData'));
        if (!$telegramUser) {
            return response()->json([
                'success' => false,
                'error' => 'Невалидные данные пользователя',
            ], 400);
        }

        $tickets = LottoTicket::forUser($telegramUser->id)
            ->with('lottoGame')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'game_name' => $ticket->lottoGame->name,
                    'game_multiplier' => $ticket->lottoGame->multiplier,
                    'stars_paid' => $ticket->stars_paid,
                    'status' => $ticket->status,
                    'is_winner' => $ticket->is_winner,
                    'winnings' => $ticket->winnings,
                    'purchased_at' => $ticket->purchased_at,
                    'drawn_at' => $ticket->drawn_at,
                ];
            });

        return response()->json([
            'success' => true,
            'tickets' => $tickets,
        ]);
    }

    /**
     * Получить статистику пользователя
     */
    public function getUserStats(Request $request)
    {
        $request->validate([
            'initData' => 'required|string',
        ]);

        $telegramUser = $this->getTelegramUserFromInitData($request->input('initData'));
        if (!$telegramUser) {
            return response()->json([
                'success' => false,
                'error' => 'Невалидные данные пользователя',
            ], 400);
        }

        $totalTickets = LottoTicket::forUser($telegramUser->id)
            ->whereIn('status', ['participating', 'won', 'lost'])
            ->count();

        $totalSpent = LottoTicket::forUser($telegramUser->id)
            ->whereIn('status', ['participating', 'won', 'lost'])
            ->sum('stars_paid');

        $totalWinnings = LottoTicket::forUser($telegramUser->id)
            ->winners()
            ->sum('winnings');

        $winningTickets = LottoTicket::forUser($telegramUser->id)
            ->winners()
            ->count();

        $todayTickets = LottoTicket::forUser($telegramUser->id)
            ->today()
            ->whereIn('status', ['participating', 'won', 'lost'])
            ->count();

        $winRate = $totalTickets > 0 ? round(($winningTickets / $totalTickets) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'stats' => [
                'total_tickets' => $totalTickets,
                'total_spent' => $totalSpent,
                'total_winnings' => $totalWinnings,
                'net_profit' => $totalWinnings - $totalSpent,
                'winning_tickets' => $winningTickets,
                'win_rate' => $winRate,
                'today_tickets' => $todayTickets,
            ],
        ]);
    }

    /**
     * Получить результаты розыгрышей
     */
    public function getDrawResults(Request $request)
    {
        $draws = LottoDraw::completed()
            ->with('lottoGame')
            ->orderBy('draw_date', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($draw) {
                return [
                    'id' => $draw->id,
                    'game_name' => $draw->lottoGame->name,
                    'game_multiplier' => $draw->lottoGame->multiplier,
                    'draw_date' => $draw->draw_date->format('d.m.Y'),
                    'total_tickets' => $draw->total_tickets,
                    'total_pool' => $draw->total_pool,
                    'winners_count' => $draw->winners_count,
                    'total_winnings' => $draw->total_winnings,
                    'win_rate' => $draw->draw_results['win_rate'] ?? 0,
                ];
            });

        return response()->json([
            'success' => true,
            'draws' => $draws,
        ]);
    }

    /**
     * Подтверждение платежа (webhook от Telegram)
     */
    public function confirmPayment(Request $request)
    {
        Log::info('Lotto payment confirmation received', $request->all());

        $update = $request->all();
        
        if (isset($update['pre_checkout_query'])) {
            $this->handlePreCheckoutQuery($update['pre_checkout_query']);
        }

        if (isset($update['message']['successful_payment'])) {
            $this->handleSuccessfulPayment($update['message']);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Создать инвойс для оплаты звёздами
     */
    private function createStarInvoice($telegramUser, $ticket, $game)
    {
        try {
            $payload = json_encode([
                'ticket_id' => $ticket->id,
                'game_id' => $game->id,
                'user_id' => $telegramUser->id,
            ]);

            // Отправляем инвойс прямо пользователю
            $response = Http::post($this->botUrl . '/sendInvoice', [
                'chat_id' => $telegramUser->telegram_id,
                'title' => "Лото билет {$game->name}",
                'description' => "Билет на лото с множителем x{$game->multiplier}. Потенциальный выигрыш: {$game->getPotentialWinnings()} ⭐",
                'payload' => $payload,
                'currency' => 'XTR', // Telegram Stars
                'prices' => json_encode([
                    [
                        'label' => "Билет {$game->name}",
                        'amount' => $game->ticket_price,
                    ]
                ]),
            ]);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('Invoice sent successfully', [
                    'ticket_id' => $ticket->id,
                    'telegram_id' => $telegramUser->telegram_id,
                    'result' => $result,
                ]);
                return true;
            }

            Log::error('Failed to send invoice', [
                'response' => $response->body(),
                'ticket_id' => $ticket->id,
                'telegram_id' => $telegramUser->telegram_id,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception sending invoice', [
                'error' => $e->getMessage(),
                'ticket_id' => $ticket->id,
            ]);
            return false;
        }
    }

    /**
     * Обработка pre-checkout запроса
     */
    private function handlePreCheckoutQuery($preCheckoutQuery)
    {
        $queryId = $preCheckoutQuery['id'];
        $payload = json_decode($preCheckoutQuery['invoice_payload'], true);

        // Проверяем, что билет существует и ещё не оплачен
        $ticket = LottoTicket::find($payload['ticket_id'] ?? 0);
        
        if (!$ticket || $ticket->status !== 'pending') {
            Http::post($this->botUrl . '/answerPreCheckoutQuery', [
                'pre_checkout_query_id' => $queryId,
                'ok' => false,
                'error_message' => 'Билет недействителен или уже оплачен',
            ]);
            return;
        }

        // Подтверждаем оплату
        Http::post($this->botUrl . '/answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $queryId,
            'ok' => true,
        ]);
    }

    /**
     * Обработка успешного платежа
     */
    private function handleSuccessfulPayment($message)
    {
        $payment = $message['successful_payment'];
        $payload = json_decode($payment['invoice_payload'], true);

        $ticket = LottoTicket::find($payload['ticket_id'] ?? 0);
        
        if (!$ticket) {
            Log::error('Ticket not found for successful payment', $payload);
            return;
        }

        // Обновляем билет
        $ticket->update([
            'status' => 'participating',
            'purchased_at' => now(),
            'payment_charge_id' => $payment['telegram_payment_charge_id'],
            'payment_data' => $payment,
        ]);

        // Создаём или обновляем розыгрыш на сегодня
        LottoDraw::getOrCreateTodayDraw($ticket->lotto_game_id);

        // Отправляем подтверждение пользователю
        $this->sendPaymentConfirmation($message['chat']['id'], $ticket);

        Log::info('Lotto ticket payment confirmed', [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'user_id' => $ticket->telegram_user_id,
        ]);
    }

    /**
     * Отправить подтверждение оплаты
     */
    private function sendPaymentConfirmation($chatId, $ticket)
    {
        $game = $ticket->lottoGame;
        
        $text = "🎟️ Билет оплачен!\n\n";
        $text .= "📄 Номер билета: {$ticket->ticket_number}\n";
        $text .= "🎰 Игра: {$game->name}\n";
        $text .= "💰 Потенциальный выигрыш: {$game->getPotentialWinnings()} ⭐\n";
        $text .= "🎲 Шанс выигрыша: " . ($game->win_chance * 100) . "%\n\n";
        $text .= "⏰ Розыгрыш пройдёт сегодня в 23:00 МСК\n";
        $text .= "🍀 Удачи!";

        Http::post($this->botUrl . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    /**
     * Получить пользователя Telegram из initData
     */
    private function getTelegramUserFromInitData($initData)
    {
        try {
            // Простая валидация для разработки
            // В продакшене здесь должна быть полная проверка подписи
            parse_str($initData, $data);
            
            if (!isset($data['user'])) {
                return null;
            }

            $userData = json_decode($data['user'], true);
            if (!$userData || !isset($userData['id'])) {
                return null;
            }

            return TelegramUser::createOrUpdate($data);
        } catch (\Exception $e) {
            Log::error('Error parsing initData', [
                'error' => $e->getMessage(),
                'initData' => $initData,
            ]);
            return null;
        }
    }
}
