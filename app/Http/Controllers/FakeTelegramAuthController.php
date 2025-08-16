<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class FakeTelegramAuthController extends Controller
{
    // Показывает форму регистрации
    public function showRegisterForm()
    {
        return view('fake-tg-register');
    }

    // Обрабатывает регистрацию
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:50',
            'username' => 'nullable|string|max:50',
            'photo_url' => 'nullable|url',
        ]);

        $user = [
            'id' => rand(1000000, 9999999),
            'first_name' => $request->input('first_name'),
            'username' => $request->input('username'),
            'photo_url' => $request->input('photo_url'),
        ];
        Session::put('fake_tg_user', $user);
        return Redirect::to('/');
    }

    // Выход
    public function logout()
    {
        Session::forget('fake_tg_user');
        return Redirect::to('/register');
    }
}
