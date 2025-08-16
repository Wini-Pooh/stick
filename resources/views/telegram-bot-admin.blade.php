<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление Telegram Bot - Stars Support</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn {
            background: #0088cc;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            margin: 8px;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #006bb3;
        }
        .btn.danger {
            background: #dc3545;
        }
        .btn.danger:hover {
            background: #c82333;
        }
        .btn.success {
            background: #28a745;
        }
        .btn.success:hover {
            background: #218838;
        }
        .result {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .header {
            text-align: center;
            color: #333;
        }
        .status {
            padding: 8px 16px;
            border-radius: 4px;
            margin: 8px 0;
            font-weight: bold;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1 class="header">🤖 Управление Telegram Bot</h1>
        <h2 class="header">⭐ Настройка для Telegram Stars платежей</h2>
        
        <div class="status warning">
            <strong>📢 Важно:</strong> Для корректной работы платежей Telegram Stars необходимо включить обработку 
            <code>pre_checkout_query</code> и <code>successful_payment</code> в webhook.
        </div>
    </div>

    <div class="card">
        <h3>🔧 Быстрые действия</h3>
        <p>Используйте эти кнопки для управления webhook вашего бота:</p>
        
        <a href="#" class="btn success" onclick="setWebhookWithStars()">
            ⭐ Установить Webhook для Stars
        </a>
        
        <a href="#" class="btn" onclick="getWebhookInfo()">
            📋 Информация о Webhook
        </a>
        
        <a href="#" class="btn danger" onclick="deleteWebhook()">
            🗑️ Удалить Webhook
        </a>
    </div>

    <div class="card">
        <h3>📊 Результат операции</h3>
        <div id="result" class="result">Нажмите на одну из кнопок выше для выполнения операции...</div>
    </div>

    <div class="card">
        <h3>ℹ️ Информация</h3>
        <p><strong>URL webhook:</strong> {{ config('app.url') }}/api/telegram/webhook</p>
        <p><strong>Бот:</strong> {{ env('TELEGRAM_BOT_USERNAME', 'Не установлен') }}</p>
        <p><strong>Окружение:</strong> {{ config('app.env') }}</p>
        
        <div class="status {{ config('app.env') === 'production' ? 'success' : 'warning' }}">
            <strong>Статус:</strong> 
            {{ config('app.env') === 'production' ? '🟢 Production (боевой режим)' : '🟡 Development (тестовый режим)' }}
        </div>
    </div>

    <script>
        async function makeRequest(url, method = 'GET') {
            const resultDiv = document.getElementById('result');
            resultDiv.textContent = '⏳ Выполняется запрос...';
            
            try {
                const response = await fetch(url, { method });
                const data = await response.json();
                
                resultDiv.textContent = JSON.stringify(data, null, 2);
                
                // Подсветка результата
                if (data.success || data.ok) {
                    resultDiv.style.borderLeft = '4px solid #28a745';
                } else {
                    resultDiv.style.borderLeft = '4px solid #dc3545';
                }
            } catch (error) {
                resultDiv.textContent = `❌ Ошибка: ${error.message}`;
                resultDiv.style.borderLeft = '4px solid #dc3545';
            }
        }

        function setWebhookWithStars() {
            makeRequest('/telegram/set-webhook-stars');
        }

        function getWebhookInfo() {
            makeRequest('/telegram/webhook-info');
        }

        function deleteWebhook() {
            if (confirm('Вы уверены, что хотите удалить webhook? Бот перестанет получать обновления!')) {
                makeRequest('/telegram/delete-webhook');
            }
        }

        // Автоматически загружаем информацию о webhook при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            getWebhookInfo();
        });
    </script>
</body>
</html>
