<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LottoGame;

class LottoGamesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LottoGame::create([
            'name' => 'Удвоение x2',
            'multiplier' => 2,
            'ticket_price' => 1,
            'win_chance' => 0.4000, // 40% шанс выигрыша
            'is_active' => true,
            'description' => 'Самый популярный тариф! Высокий шанс удвоить свои звёзды.',
            'color' => '#4CAF50', // Зелёный
        ]);

        LottoGame::create([
            'name' => 'Утроение x3',
            'multiplier' => 3,
            'ticket_price' => 1,
            'win_chance' => 0.2500, // 25% шанс выигрыша
            'is_active' => true,
            'description' => 'Золотая середина между риском и выигрышем.',
            'color' => '#FF9800', // Оранжевый
        ]);

        LottoGame::create([
            'name' => 'Джекпот x10',
            'multiplier' => 10,
            'ticket_price' => 1,
            'win_chance' => 0.0800, // 8% шанс выигрыша
            'is_active' => true,
            'description' => 'Рискованная игра с огромным выигрышем!',
            'color' => '#E91E63', // Розовый
        ]);

        LottoGame::create([
            'name' => 'Премиум x5',
            'multiplier' => 5,
            'ticket_price' => 2,
            'win_chance' => 0.1500, // 15% шанс выигрыша
            'is_active' => true,
            'description' => 'Премиум билет с увеличенной ставкой и выигрышем.',
            'color' => '#9C27B0', // Фиолетовый
        ]);

        LottoGame::create([
            'name' => 'Мега x20',
            'multiplier' => 20,
            'ticket_price' => 3,
            'win_chance' => 0.0300, // 3% шанс выигрыша
            'is_active' => true,
            'description' => 'Мега розыгрыш для самых смелых игроков!',
            'color' => '#F44336', // Красный
        ]);
    }
}
