@extends('layouts.app')

@section('content')
<div class="container" style="max-width:400px;margin-top:60px;">
    <h2 class="mb-4">Имитация входа через Telegram</h2>
    <form method="POST" action="{{ url('/register') }}">
        @csrf
        <div class="mb-3">
            <label for="first_name" class="form-label">Имя <span style="color:red">*</span></label>
            <input type="text" class="form-control" id="first_name" name="first_name" required value="{{ old('first_name') }}">
        </div>
        <div class="mb-3">
            <label for="username" class="form-label">Username (опционально)</label>
            <input type="text" class="form-control" id="username" name="username" value="{{ old('username') }}">
        </div>
        <div class="mb-3">
            <label for="photo_url" class="form-label">URL аватарки (опционально)</label>
            <input type="url" class="form-control" id="photo_url" name="photo_url" value="{{ old('photo_url') }}" placeholder="https://...">
        </div>
        <button type="submit" class="btn btn-primary w-100">Войти как Telegram-пользователь</button>
    </form>
</div>
@endsection
