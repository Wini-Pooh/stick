<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Mini App</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
            min-height: 100vh;
        }
        
        .miniapp-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 16px;
        }
        
        .miniapp-header {
            text-align: center;
            margin-bottom: 24px;
            padding: 20px;
            background: var(--tg-theme-secondary-bg-color, #f0f0f0);
            border-radius: 12px;
        }
        
        .miniapp-section {
            background: var(--tg-theme-secondary-bg-color, #f8f8f8);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        
        .miniapp-button {
            background: var(--tg-theme-button-color, #007bff);
            color: var(--tg-theme-button-text-color, #ffffff);
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin: 8px 0;
            transition: opacity 0.2s;
        }
        
        .miniapp-button:hover {
            opacity: 0.8;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--tg-theme-button-color, #007bff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .debug-info {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 12px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online {
            background: #28a745;
        }
        
        .status-offline {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="miniapp-container">
        <div class="miniapp-header">
            <h1 class="h3 mb-2">🚀 Telegram Mini App</h1>
            <p class="text-muted mb-0">Профиль пользователя и отладочная информация</p>
        </div>

        <div class="miniapp-section">
            <h2 class="h5 mb-3">👤 Профиль пользователя</h2>
            <div id="profile-content" class="text-center text-muted">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Загрузка профиля...
            </div>
            <button class="miniapp-button mt-3" onclick="loadProfile()">🔄 Обновить профиль</button>
        </div>

        <div class="miniapp-section">
            <h2 class="h5 mb-3">🐛 Отладочная информация</h2>
            <div id="debug-content" class="text-center text-muted">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Загрузка отладочной информации...
            </div>
            <button class="miniapp-button mt-3" onclick="loadDebugInfo()">🔄 Обновить debug</button>
        </div>

        <div class="miniapp-section">
            <h2 class="h5 mb-3">⚙️ Telegram WebApp API</h2>
            <div id="webapp-info" class="debug-info"></div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Инициализация Telegram WebApp
        let tg = window.Telegram.WebApp;
        
        // Развернуть WebApp на весь экран
        tg.expand();
        
        // Применить тему Telegram
        document.body.style.background = tg.themeParams.bg_color || '#ffffff';
        document.body.style.color = tg.themeParams.text_color || '#000000';
        
        // Получить initData из URL или Telegram WebApp
        const urlParams = new URLSearchParams(window.location.search);
        const initDataFromUrl = urlParams.get('initData');
        const initData = initDataFromUrl || tg.initData || '';
        
        console.log('InitData:', initData);
        console.log('Telegram WebApp object:', tg);
        
        // Показать информацию о Telegram WebApp
        function showWebAppInfo() {
            const webappInfo = {
                initData: tg.initData,
                initDataUnsafe: tg.initDataUnsafe,
                version: tg.version,
                platform: tg.platform,
                colorScheme: tg.colorScheme,
                themeParams: tg.themeParams,
                isExpanded: tg.isExpanded,
                viewportHeight: tg.viewportHeight,
                viewportStableHeight: tg.viewportStableHeight,
                headerColor: tg.headerColor,
                backgroundColor: tg.backgroundColor,
                isClosingConfirmationEnabled: tg.isClosingConfirmationEnabled,
                isVerticalSwipesEnabled: tg.isVerticalSwipesEnabled
            };
            
            document.getElementById('webapp-info').textContent = JSON.stringify(webappInfo, null, 2);
        }
        
        // Загрузка профиля пользователя
        async function loadProfile() {
            try {
                const profileContent = document.getElementById('profile-content');
                profileContent.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Загрузка...</div>';
                
                if (!initData) {
                    throw new Error('InitData отсутствует');
                }
                
                const response = await fetch('{{ route("miniapp.profile") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Telegram-Init-Data': initData
                    },
                    body: JSON.stringify({ initData: initData })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Ошибка загрузки профиля');
                }
                
                if (data.user) {
                    const user = data.user;
                    const dbUser = data.database_user;
                    profileContent.innerHTML = `
                        <div class="user-info">
                            <div class="avatar">
                                ${user.first_name ? user.first_name.charAt(0) : '?'}
                            </div>
                            <div class="flex-grow-1 text-start">
                                <div class="fw-bold">
                                    ${user.first_name || ''} ${user.last_name || ''}
                                    ${user.username ? '<small class="text-muted">@' + user.username + '</small>' : ''}
                                </div>
                                <small class="text-muted">ID: ${user.id}</small>
                                ${user.language_code ? '<br><small class="text-muted">Язык: ' + user.language_code + '</small>' : ''}
                                ${user.is_premium ? '<br><small class="text-warning">⭐ Premium пользователь</small>' : ''}
                            </div>
                        </div>
                        ${dbUser ? `
                        <div class="mt-3 p-3 bg-light rounded">
                            <h6 class="mb-2">📊 Статистика:</h6>
                            <small class="d-block">Визитов: ${dbUser.visits_count}</small>
                            <small class="d-block">Первый визит: ${new Date(dbUser.first_seen_at).toLocaleString()}</small>
                            <small class="d-block">Последний визит: ${new Date(dbUser.last_seen_at).toLocaleString()}</small>
                            <span class="status-indicator ${dbUser.is_online ? 'status-online' : 'status-offline'}"></span>
                            <small>${dbUser.is_online ? 'Онлайн' : 'Оффлайн'}</small>
                        </div>
                        ` : ''}
                    `;
                } else {
                    profileContent.innerHTML = '<div class="alert alert-warning">Данные пользователя не найдены</div>';
                }
                
            } catch (error) {
                console.error('Ошибка загрузки профиля:', error);
                document.getElementById('profile-content').innerHTML = 
                    `<div class="alert alert-danger">Ошибка: ${error.message}</div>`;
            }
        }
        
        // Загрузка отладочной информации
        async function loadDebugInfo() {
            try {
                const debugContent = document.getElementById('debug-content');
                debugContent.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Загрузка...</div>';
                
                if (!initData) {
                    throw new Error('InitData отсутствует');
                }
                
                const response = await fetch('{{ route("miniapp.debug") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Telegram-Init-Data': initData
                    },
                    body: JSON.stringify({ initData: initData })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Ошибка загрузки debug информации');
                }
                
                debugContent.innerHTML = `<div class="debug-info">${JSON.stringify(data.debug_info, null, 2)}</div>`;
                
            } catch (error) {
                console.error('Ошибка загрузки debug информации:', error);
                document.getElementById('debug-content').innerHTML = 
                    `<div class="alert alert-danger">Ошибка: ${error.message}</div>`;
            }
        }
        
        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            showWebAppInfo();
            
            // Если есть initData, загружаем данные
            if (initData) {
                loadProfile();
                loadDebugInfo();
            } else {
                document.getElementById('profile-content').innerHTML = 
                    '<div class="alert alert-warning">InitData отсутствует. Откройте приложение через Telegram.</div>';
                document.getElementById('debug-content').innerHTML = 
                    '<div class="alert alert-warning">InitData отсутствует. Откройте приложение через Telegram.</div>';
            }
        });
        
        // Обработка событий Telegram WebApp
        tg.onEvent('mainButtonClicked', function() {
            tg.sendData('main_button_clicked');
        });
        
        tg.onEvent('backButtonClicked', function() {
            tg.close();
        });
        
        // Показать главную кнопку
        tg.MainButton.setText('Закрыть приложение');
        tg.MainButton.show();
        tg.MainButton.onClick(function() {
            tg.close();
        });
        
        // Включить подтверждение закрытия
        tg.enableClosingConfirmation();
    </script>
</body>
</html>
