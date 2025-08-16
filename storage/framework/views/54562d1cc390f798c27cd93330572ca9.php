<?php
    $fakeTgUser = session('fake_tg_user');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Звёздное Лото | Telegram Mini App</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        window.FAKE_TG_USER = <?php echo json_encode($fakeTgUser, 15, 512) ?>;
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            touch-action: manipulation;
        }
        
        body {
            background: linear-gradient(135deg, var(--tg-theme-bg-color, #1a1a2e) 0%, var(--tg-theme-secondary-bg-color, #16213e) 100%);
            color: var(--tg-theme-text-color, #ffffff);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            overscroll-behavior: contain;
            overflow-x: hidden;
            min-height: 100vh;
        }
        
        .app-container {
            max-width: 420px;
            margin: 0 auto;
            padding: 20px 16px;
            min-height: 100vh;
        }
        
        .header {
            text-align: center;
            margin-bottom: 24px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(45deg, #FFD700, #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 16px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #FFD700, #FFA500);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #FFD700;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .games-section {
            margin-bottom: 24px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            text-align: center;
        }
        
        .games-grid {
            display: grid;
            gap: 16px;
        }
        
        .game-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .game-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .game-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--game-color, #FFD700), var(--game-color-light, #FFED4E));
        }
        
        .game-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .game-name {
            font-size: 18px;
            font-weight: 700;
        }
        
        .game-multiplier {
            background: linear-gradient(45deg, #FF6B6B, #FF8E8E);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .game-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .game-info-item {
            text-align: center;
        }
        
        .game-info-value {
            font-size: 16px;
            font-weight: 600;
            color: #FFD700;
        }
        
        .game-info-label {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 2px;
        }
        
        .game-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 8px 12px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
        }
        
        .game-stat {
            text-align: center;
        }
        
        .game-stat-value {
            font-size: 14px;
            font-weight: 600;
            color: #FFD700;
        }
        
        .game-stat-label {
            font-size: 10px;
            opacity: 0.7;
        }
        
        .buy-button {
            width: 100%;
            background: linear-gradient(45deg, var(--tg-theme-button-color, #007bff), var(--tg-theme-button-color, #0056b3));
            color: var(--tg-theme-button-text-color, #ffffff);
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .buy-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }
        
        .buy-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .buy-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .buy-button:hover::before {
            left: 100%;
        }
        
        .recent-section {
            margin-bottom: 24px;
        }
        
        .recent-list {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }
        
        .recent-item {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .recent-info {
            flex: 1;
        }
        
        .recent-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .recent-subtitle {
            font-size: 12px;
            opacity: 0.7;
        }
        
        .recent-status {
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-won {
            background: #4CAF50;
            color: white;
        }
        
        .status-lost {
            background: #F44336;
            color: white;
        }
        
        .status-participating {
            background: #FF9800;
            color: white;
        }
        
        .loading {
            text-align: center;
            padding: 40px 20px;
            opacity: 0.7;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 32px;
            height: 32px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #FFD700;
            animation: spin 1s ease-in-out infinite;
            margin-bottom: 12px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .error-message {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid rgba(244, 67, 54, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin: 16px 0;
            color: #F44336;
            text-align: center;
            font-size: 14px;
        }
        
        .success-message {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin: 16px 0;
            color: #4CAF50;
            text-align: center;
            font-size: 14px;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            opacity: 0.7;
            font-size: 12px;
        }
        
        @media (max-width: 360px) {
            .app-container {
                padding: 16px 12px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .game-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Шапка приложения -->
        <div class="header">
            <h1>⭐ Звёздное Лото</h1>
            <p>Донатьте звёзды и выигрывайте в ежедневных розыгрышах!</p>
            <p style="font-size: 12px; margin-top: 8px; opacity: 0.8;">
                💡 Счёт для оплаты будет отправлен в чат с ботом
            </p>
            <div class="user-info">
                <div class="user-avatar" id="userAvatar">?</div>
                <div class="user-name" id="userName">Загрузка...</div>
            </div>
        </div>

        <!-- Статистика пользователя -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="totalTickets">-</div>
                <div class="stat-label">Билетов куплено</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="totalWinnings">-</div>
                <div class="stat-label">Выиграно ⭐</div>
            </div>
        </div>

        <!-- Доступные игры -->
        <div class="games-section">
            <h2 class="section-title">🎰 Доступные игры</h2>
            <div class="games-grid" id="gamesGrid">
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <div>Загрузка игр...</div>
                </div>
            </div>
        </div>

        <!-- Последние билеты -->
        <div class="recent-section">
            <h2 class="section-title">🎟️ Мои билеты</h2>
            <div class="recent-list" id="recentTickets">
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <div>Загрузка билетов...</div>
                </div>
            </div>
        </div>

        <!-- Футер -->
        <div class="footer">
            <p>Розыгрыши проводятся ежедневно в 23:00 МСК</p>
        </div>
    </div>

    <script>
        // Инициализация Telegram WebApp
        const tg = window.Telegram.WebApp;
        tg.expand();
        tg.ready();
        
        // Получаем CSRF токен
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Получаем цвета из темы Telegram
        const backgroundColor = tg.themeParams.bg_color || '#1a1a2e';
        const textColor = tg.themeParams.text_color || '#ffffff';
        const buttonColor = tg.themeParams.button_color || '#007bff';
        const buttonTextColor = tg.themeParams.button_text_color || '#ffffff';
        
        // Применяем цвета к CSS переменным
        document.documentElement.style.setProperty('--tg-theme-bg-color', backgroundColor);
        document.documentElement.style.setProperty('--tg-theme-text-color', textColor);
        document.documentElement.style.setProperty('--tg-theme-button-color', buttonColor);
        document.documentElement.style.setProperty('--tg-theme-button-text-color', buttonTextColor);
        
        // Получаем initData
        function getInitData() {
            if (window.FAKE_TG_USER) {
                // Для тестирования с fake пользователем
                return `user=${encodeURIComponent(JSON.stringify(window.FAKE_TG_USER))}`;
            }
            
            return tg.initData || '';
        }
        
        // Загрузка данных пользователя
        function loadUserInfo() {
            const initData = getInitData();
            if (!initData) {
                document.getElementById('userName').textContent = 'Гость';
                return;
            }
            
            try {
                const urlParams = new URLSearchParams(initData);
                const userStr = urlParams.get('user');
                if (userStr) {
                    const user = JSON.parse(userStr);
                    document.getElementById('userName').textContent = user.first_name || 'Пользователь';
                    
                    // Устанавливаем аватар
                    const avatar = document.getElementById('userAvatar');
                    if (user.first_name) {
                        avatar.textContent = user.first_name.charAt(0).toUpperCase();
                    }
                }
            } catch (e) {
                console.error('Error parsing user data:', e);
                document.getElementById('userName').textContent = 'Пользователь';
            }
        }
        
        // Загрузка статистики пользователя
        async function loadUserStats() {
            const initData = getInitData();
            if (!initData) return;
            
            try {
                const response = await fetch('/api/lotto/user-stats', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ initData }),
                });
                
                const data = await response.json();
                if (data.success) {
                    document.getElementById('totalTickets').textContent = data.stats.total_tickets;
                    document.getElementById('totalWinnings').textContent = data.stats.total_winnings;
                }
            } catch (error) {
                console.error('Error loading user stats:', error);
            }
        }
        
        // Загрузка доступных игр
        async function loadGames() {
            try {
                const response = await fetch('/api/lotto/games');
                const data = await response.json();
                
                if (data.success) {
                    renderGames(data.games);
                } else {
                    showError('Не удалось загрузить игры');
                }
            } catch (error) {
                console.error('Error loading games:', error);
                showError('Ошибка загрузки игр');
            }
        }
        
        // Отображение игр
        function renderGames(games) {
            const container = document.getElementById('gamesGrid');
            
            if (games.length === 0) {
                container.innerHTML = '<div class="loading">Игры временно недоступны</div>';
                return;
            }
            
            container.innerHTML = games.map(game => {
                const gameColor = game.color || '#FFD700';
                const gameColorLight = adjustColor(gameColor, 20);
                
                return `
                    <div class="game-card" style="--game-color: ${gameColor}; --game-color-light: ${gameColorLight};" data-game-id="${game.id}">
                        <div class="game-header">
                            <div class="game-name">${game.name}</div>
                            <div class="game-multiplier">x${game.multiplier}</div>
                        </div>
                        <div class="game-info">
                            <div class="game-info-item">
                                <div class="game-info-value">${game.ticket_price} ⭐</div>
                                <div class="game-info-label">Цена билета</div>
                            </div>
                            <div class="game-info-item">
                                <div class="game-info-value">${game.potential_winnings} ⭐</div>
                                <div class="game-info-label">Возможный выигрыш</div>
                            </div>
                        </div>
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="game-stat-value">${(game.win_chance * 100).toFixed(2)}%</div>
                                <div class="game-stat-label">Шанс выигрыша</div>
                            </div>
                            <div class="game-stat">
                                <div class="game-stat-value">${game.today_tickets}</div>
                                <div class="game-stat-label">Билетов сегодня</div>
                            </div>
                            <div class="game-stat">
                                <div class="game-stat-value">${game.today_pool} ⭐</div>
                                <div class="game-stat-label">Банк</div>
                            </div>
                        </div>
                        <button class="buy-button" onclick="buyTicket(${game.id})" data-price="${game.ticket_price}">
                            Купить билет за ${game.ticket_price} ⭐
                        </button>
                    </div>
                `;
            }).join('');
        }
        
        // Покупка билета
        async function buyTicket(gameId) {
            const initData = getInitData();
            if (!initData) {
                showError('Необходимо авторизоваться через Telegram');
                return;
            }
            
            try {
                const button = event.target;
                button.disabled = true;
                button.textContent = 'Отправка счёта...';
                
                const response = await fetch('/api/lotto/buy-ticket', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ 
                        game_id: gameId,
                        initData 
                    }),
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess(`Билет №${data.ticket.ticket_number} создан! ${data.message}`);
                    
                    // Показываем пользователю инструкцию
                    setTimeout(() => {
                        showSuccess('Проверьте чат с ботом @' + (tg.initDataUnsafe?.start_param || 'Stickap_bot') + ' для оплаты билета!');
                    }, 2000);
                    
                    // Обновляем статистику через некоторое время
                    setTimeout(() => {
                        loadUserStats();
                        loadUserTickets();
                    }, 5000);
                } else {
                    showError(data.error || 'Ошибка создания билета');
                }
            } catch (error) {
                console.error('Error buying ticket:', error);
                showError('Ошибка при покупке билета');
            } finally {
                setTimeout(() => {
                    const button = event.target;
                    button.disabled = false;
                    button.textContent = `Купить билет за ${button.getAttribute('data-price') || '?'} ⭐`;
                }, 3000);
            }
        }
        
        // Загрузка билетов пользователя
        async function loadUserTickets() {
            const initData = getInitData();
            if (!initData) {
                document.getElementById('recentTickets').innerHTML = '<div class="loading">Необходимо авторизоваться</div>';
                return;
            }
            
            try {
                const response = await fetch('/api/lotto/user-tickets', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ initData }),
                });
                
                const data = await response.json();
                
                if (data.success) {
                    renderUserTickets(data.tickets);
                } else {
                    showError('Не удалось загрузить билеты');
                }
            } catch (error) {
                console.error('Error loading user tickets:', error);
                showError('Ошибка загрузки билетов');
            }
        }
        
        // Отображение билетов пользователя
        function renderUserTickets(tickets) {
            const container = document.getElementById('recentTickets');
            
            if (tickets.length === 0) {
                container.innerHTML = '<div class="loading">У вас пока нет билетов</div>';
                return;
            }
            
            container.innerHTML = tickets.slice(0, 10).map(ticket => {
                const statusClass = `status-${ticket.status === 'won' ? 'won' : ticket.status === 'lost' ? 'lost' : 'participating'}`;
                const statusText = ticket.status === 'won' ? `Выиграл ${ticket.winnings} ⭐` : 
                                 ticket.status === 'lost' ? 'Проиграл' : 
                                 ticket.status === 'participating' ? 'Участвует' :
                                 'Ожидает оплаты';
                
                return `
                    <div class="recent-item">
                        <div class="recent-info">
                            <div class="recent-title">${ticket.ticket_number}</div>
                            <div class="recent-subtitle">${ticket.game_name} • ${ticket.stars_paid} ⭐</div>
                        </div>
                        <div class="recent-status ${statusClass}">${statusText}</div>
                    </div>
                `;
            }).join('');
        }
        
        // Вспомогательные функции
        function adjustColor(color, amount) {
            return '#' + color.replace(/^#/, '').replace(/../g, color => ('0'+Math.min(255, Math.max(0, parseInt(color, 16) + amount)).toString(16)).substr(-2));
        }
        
        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            
            document.querySelector('.app-container').insertBefore(errorDiv, document.querySelector('.games-section'));
            
            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        }
        
        function showSuccess(message) {
            const successDiv = document.createElement('div');
            successDiv.className = 'success-message';
            successDiv.textContent = message;
            
            document.querySelector('.app-container').insertBefore(successDiv, document.querySelector('.games-section'));
            
            setTimeout(() => {
                successDiv.remove();
            }, 5000);
        }
        
        // Инициализация приложения
        document.addEventListener('DOMContentLoaded', function() {
            loadUserInfo();
            loadUserStats();
            loadGames();
            loadUserTickets();
            
            // Обновляем данные каждые 30 секунд
            setInterval(() => {
                loadUserStats();
                loadGames();
                loadUserTickets();
            }, 30000);
        });
        
        // Обработка успешных платежей (если возвращаемся из Telegram)
        if (window.location.search.includes('payment=success')) {
            setTimeout(() => {
                showSuccess('Билет успешно оплачен! Удачи в розыгрыше!');
                loadUserStats();
                loadUserTickets();
            }, 1000);
        }
    </script>
</body>
</html>
          <?php /**PATH C:\OSPanel\domains\tgstick\resources\views/miniapp/index.blade.php ENDPATH**/ ?>