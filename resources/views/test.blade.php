<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Mini App - Test Page</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <div class="test-card">
                    <h1 class="text-center mb-4">🚀 Telegram Mini App - Test Page</h1>
                    <p class="text-center text-muted">Эта страница поможет вам протестировать Telegram Mini App.</p>
                </div>

                <div class="test-card">
                    <h2 class="h4 mb-3">📱 Быстрые ссылки</h2>
                    <div class="d-grid gap-2">
                        <a href="/miniapp" class="btn btn-primary btn-lg">🚀 Открыть Mini App</a>
                        <a href="/miniapp/test" class="btn btn-secondary">🧪 Test Endpoint</a>
                        <a href="/telegram/webhook-info" class="btn btn-info">📡 Webhook Info</a>
                        <a href="/telegram/set-webhook" class="btn btn-warning">⚙️ Set Webhook</a>
                    </div>
                </div>

                <div class="test-card">
                    <h2 class="h4 mb-3">🔗 Для настройки в BotFather</h2>
                    <div class="status-info">
                        <strong>URL Mini App:</strong><br>
                        <code id="miniapp-url" class="user-select-all">{{ env('APP_URL') }}/miniapp</code>
                        <small class="d-block mt-1 text-muted">Нажмите, чтобы скопировать</small>
                    </div>
                </div>

                <div class="test-card">
                    <h2 class="h4 mb-3">🧪 Тест API</h2>
                    <button class="btn btn-primary" onclick="testApi()">Тестировать API</button>
                    <div id="api-result" class="mt-3"></div>
                </div>

                <div class="test-card">
                    <h2 class="h4 mb-3">📋 Конфигурация</h2>
                    <pre class="bg-light p-3 rounded"><code>Bot Token: {{ env('TELEGRAM_BOT_TOKEN') ? 'Настроен ✅' : 'Не настроен ❌' }}
Bot Username: {{ env('TELEGRAM_BOT_USERNAME', 'Не указан') }}
App URL: {{ env('APP_URL') }}
Environment: {{ app()->environment() }}</code></pre>
                </div>

                <div class="test-card">
                    <h2 class="h4 mb-3">📖 Инструкции</h2>
                    <ol class="list-group list-group-numbered">
                        <li class="list-group-item">Убедитесь, что в <code>.env</code> настроены <code>TELEGRAM_BOT_TOKEN</code> и <code>TELEGRAM_BOT_USERNAME</code></li>
                        <li class="list-group-item">Создайте Mini App в @BotFather с URL: <code>{{ env('APP_URL') }}/miniapp</code></li>
                        <li class="list-group-item">Настройте webhook (опционально): <a href="/telegram/set-webhook" class="text-decoration-none">установить webhook</a></li>
                        <li class="list-group-item">Найдите вашего бота в Telegram и отправьте <code>/start</code></li>
                        <li class="list-group-item">Нажмите кнопку "🚀 Открыть Mini App"</li>
                    </ol>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        async function testApi() {
            const resultDiv = document.getElementById('api-result');
            resultDiv.innerHTML = '<div class="status-info">Тестирование...</div>';
            
            try {
                const response = await fetch('/miniapp/test');
                const data = await response.json();
                
                if (response.ok) {
                    resultDiv.innerHTML = `
                        <div class="status-success">✅ API работает!</div>
                        <pre class="bg-light p-3 rounded mt-2"><code>${JSON.stringify(data, null, 2)}</code></pre>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="status-error">❌ Ошибка: ${response.status}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="status-error">❌ Ошибка: ${error.message}</div>`;
            }
        }

        // Копирование URL в буфер обмена
        document.getElementById('miniapp-url').addEventListener('click', function() {
            navigator.clipboard.writeText(this.textContent).then(() => {
                // Показываем toast уведомление
                const toast = document.createElement('div');
                toast.className = 'toast position-fixed top-0 end-0 m-3';
                toast.setAttribute('role', 'alert');
                toast.innerHTML = `
                    <div class="toast-header">
                        <strong class="me-auto">📋 Скопировано</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">URL скопирован в буфер обмена!</div>
                `;
                document.body.appendChild(toast);
                
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                
                toast.addEventListener('hidden.bs.toast', () => {
                    document.body.removeChild(toast);
                });
            });
        });
    </script>
</body>
</html>
