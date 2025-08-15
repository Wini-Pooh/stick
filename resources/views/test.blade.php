<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Mini App - Test Page</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .button {
            background: #3390ec;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin: 8px 0;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .button:hover {
            background: #2b7bc6;
        }
        .status {
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
        }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🚀 Telegram Mini App - Test Page</h1>
        <p>Эта страница поможет вам протестировать Telegram Mini App.</p>
    </div>

    <div class="card">
        <h2>📱 Быстрые ссылки</h2>
        <a href="/miniapp" class="button">🚀 Открыть Mini App</a>
        <a href="/miniapp/test" class="button">🧪 Test Endpoint</a>
        <a href="/telegram/webhook-info" class="button">📡 Webhook Info</a>
        <a href="/telegram/set-webhook" class="button">⚙️ Set Webhook</a>
    </div>

    <div class="card">
        <h2>🔗 Для настройки в BotFather</h2>
        <div class="info">
            <strong>URL Mini App:</strong><br>
            <code id="miniapp-url">{{ env('APP_URL') }}/miniapp</code>
        </div>
    </div>

    <div class="card">
        <h2>🧪 Тест API</h2>
        <button class="button" onclick="testApi()">Тестировать API</button>
        <div id="api-result"></div>
    </div>

    <div class="card">
        <h2>📋 Конфигурация</h2>
        <pre id="config">
Bot Token: {{ env('TELEGRAM_BOT_TOKEN') ? 'Настроен ✅' : 'Не настроен ❌' }}
Bot Username: {{ env('TELEGRAM_BOT_USERNAME', 'Не указан') }}
App URL: {{ env('APP_URL') }}
Environment: {{ app()->environment() }}
        </pre>
    </div>

    <div class="card">
        <h2>📖 Инструкции</h2>
        <ol>
            <li>Убедитесь, что в <code>.env</code> настроены <code>TELEGRAM_BOT_TOKEN</code> и <code>TELEGRAM_BOT_USERNAME</code></li>
            <li>Создайте Mini App в @BotFather с URL: <code>{{ env('APP_URL') }}/miniapp</code></li>
            <li>Настройте webhook (опционально): <a href="/telegram/set-webhook">установить webhook</a></li>
            <li>Найдите вашего бота в Telegram и отправьте <code>/start</code></li>
            <li>Нажмите кнопку "🚀 Открыть Mini App"</li>
        </ol>
    </div>

    <script>
        async function testApi() {
            const resultDiv = document.getElementById('api-result');
            resultDiv.innerHTML = '<div class="info">Тестирование...</div>';
            
            try {
                const response = await fetch('/miniapp/test');
                const data = await response.json();
                
                if (response.ok) {
                    resultDiv.innerHTML = `
                        <div class="success">✅ API работает!</div>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="error">❌ Ошибка: ${response.status}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="error">❌ Ошибка: ${error.message}</div>`;
            }
        }

        // Копирование URL в буфер обмена
        document.getElementById('miniapp-url').addEventListener('click', function() {
            navigator.clipboard.writeText(this.textContent);
            alert('URL скопирован в буфер обмена!');
        });
    </script>
</body>
</html>
