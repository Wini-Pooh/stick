<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Mini App</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
            padding: 16px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 24px;
            padding: 20px;
            background: var(--tg-theme-secondary-bg-color, #f0f0f0);
            border-radius: 12px;
        }
        
        .profile-section, .debug-section {
            background: var(--tg-theme-secondary-bg-color, #f8f8f8);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--tg-theme-text-color, #000);
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
            background: var(--tg-theme-button-color, #3390ec);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .user-details {
            flex: 1;
        }
        
        .username {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .user-id {
            font-size: 14px;
            color: var(--tg-theme-hint-color, #999);
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
        
        .loading {
            text-align: center;
            color: var(--tg-theme-hint-color, #999);
            padding: 20px;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .button {
            background: var(--tg-theme-button-color, #3390ec);
            color: var(--tg-theme-button-text-color, #ffffff);
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin: 8px 0;
        }
        
        .button:hover {
            opacity: 0.8;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online {
            background: #4caf50;
        }
        
        .status-offline {
            background: #f44336;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Telegram Mini App</h1>
            <p>Профиль пользователя и отладочная информация</p>
        </div>

        <div class="profile-section">
            <h2 class="section-title">👤 Профиль пользователя</h2>
            <div id="profile-content" class="loading">
                Загрузка профиля...
            </div>
            <button class="button" onclick="loadProfile()">🔄 Обновить профиль</button>
        </div>

        <div class="debug-section">
            <h2 class="section-title">🐛 Отладочная информация</h2>
            <div id="debug-content" class="loading">
                Загрузка отладочной информации...
            </div>
            <button class="button" onclick="loadDebugInfo()">🔄 Обновить debug</button>
        </div>

        <div class="debug-section">
            <h2 class="section-title">⚙️ Telegram WebApp API</h2>
            <div id="webapp-info" class="debug-info"></div>
        </div>
    </div>

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
                profileContent.innerHTML = '<div class="loading">Загрузка...</div>';
                
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
                    profileContent.innerHTML = `
                        <div class="user-info">
                            <div class="avatar">
                                ${user.first_name ? user.first_name.charAt(0) : '?'}
                            </div>
                            <div class="user-details">
                                <div class="username">
                                    ${user.first_name || ''} ${user.last_name || ''}
                                    ${user.username ? '@' + user.username : ''}
                                </div>
                                <div class="user-id">ID: ${user.id}</div>
                                ${user.language_code ? `<div class="user-id">Язык: ${user.language_code}</div>` : ''}
                                ${user.is_premium ? '<div class="user-id">⭐ Premium пользователь</div>' : ''}
                            </div>
                        </div>
                        <div style="font-size: 14px; color: var(--tg-theme-hint-color, #999);">
                            Дата авторизации: ${data.auth_date ? new Date(data.auth_date * 1000).toLocaleString() : 'N/A'}
                        </div>
                    `;
                } else {
                    profileContent.innerHTML = '<div class="error">Данные пользователя не найдены</div>';
                }
                
            } catch (error) {
                console.error('Ошибка загрузки профиля:', error);
                document.getElementById('profile-content').innerHTML = 
                    `<div class="error">Ошибка: ${error.message}</div>`;
            }
        }
        
        // Загрузка отладочной информации
        async function loadDebugInfo() {
            try {
                const debugContent = document.getElementById('debug-content');
                debugContent.innerHTML = '<div class="loading">Загрузка...</div>';
                
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
                    `<div class="error">Ошибка: ${error.message}</div>`;
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
                    '<div class="error">InitData отсутствует. Откройте приложение через Telegram.</div>';
                document.getElementById('debug-content').innerHTML = 
                    '<div class="error">InitData отсутствует. Откройте приложение через Telegram.</div>';
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
