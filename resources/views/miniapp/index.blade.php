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
        
        /* Стили для игры Змейка */
        .snake-game {
            text-align: center;
            background: var(--tg-theme-bg-color, #ffffff);
            border-radius: 12px;
            padding: 16px;
        }
        
        #gameCanvas {
            border: 2px solid var(--tg-theme-button-color, #007bff);
            border-radius: 8px;
            background: #000;
            max-width: 100%;
            height: auto;
        }
        
        .game-controls {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            max-width: 200px;
            margin: 16px auto;
        }
        
        .control-btn {
            background: var(--tg-theme-button-color, #007bff);
            color: var(--tg-theme-button-text-color, #ffffff);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 16px;
            cursor: pointer;
            touch-action: manipulation;
        }
        
        .control-btn:active {
            opacity: 0.7;
        }
        
        .game-stats {
            display: flex;
            justify-content: space-between;
            margin: 16px 0;
            font-weight: bold;
        }
        
        .empty-control {
            grid-column: 1;
        }
        
        .up-btn {
            grid-column: 2;
        }
        
        .left-btn {
            grid-column: 1;
            grid-row: 2;
        }
        
        .right-btn {
            grid-column: 3;
            grid-row: 2;
        }
        
        .down-btn {
            grid-column: 2;
            grid-row: 3;
        }
    </style>
</head>
<body>
    <div class="miniapp-container">
        <div class="miniapp-header">
            <h1 class="h3 mb-2">� Snake Game & Profile</h1>
            <p class="text-muted mb-0">Змейка и профиль пользователя</p>
        </div>

        <div class="miniapp-section">
            <h2 class="h5 mb-3">👤 Профиль пользователя</h2>
            <div id="profile-content" class="text-center text-muted">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Загрузка профиля...
            </div>
            <button class="miniapp-button mt-3" onclick="loadProfile()">🔄 Обновить профиль</button>
        </div>

        <div class="miniapp-section snake-game">
            <h2 class="h5 mb-3">� Змейка</h2>
            <canvas id="gameCanvas" width="300" height="300"></canvas>
            
            <div class="game-stats">
                <span>Счёт: <span id="score">0</span></span>
                <span>Рекорд: <span id="highScore">0</span></span>
            </div>
            
            <div class="game-controls">
                <div class="empty-control"></div>
                <button class="control-btn up-btn" onclick="changeDirection('up')">↑</button>
                <div></div>
                <button class="control-btn left-btn" onclick="changeDirection('left')">←</button>
                <button class="control-btn" onclick="toggleGame()" id="playBtn">▶️ ИГРАТЬ</button>
                <button class="control-btn right-btn" onclick="changeDirection('right')">→</button>
                <div></div>
                <button class="control-btn down-btn" onclick="changeDirection('down')">↓</button>
                <div></div>
            </div>
            
            <p class="text-muted mt-2">
                <small>Управление: кнопки или свайпы по экрану</small>
            </p>
        </div>

        <div class="miniapp-section">
            <h2 class="h5 mb-3">🔧 Тестирование соединения</h2>
            <div id="test-content" class="text-center text-muted">
                Нажмите кнопку для тестирования соединения
            </div>
            <button class="miniapp-button mt-3" onclick="testConnection()">🧪 Тест POST запроса</button>
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
        const hashParams = new URLSearchParams(window.location.hash.substring(1));
        
        // Попробуем получить initData из разных источников
        let initData = '';
        
        // 1. Из query параметров URL
        const initDataFromUrl = urlParams.get('initData');
        
        // 2. Из hash фрагмента (tgWebAppData)
        const tgWebAppData = hashParams.get('tgWebAppData');
        
        // 3. Из Telegram WebApp API
        const tgInitData = tg.initData || '';
        
        // Выбираем первый доступный
        if (tgWebAppData) {
            initData = decodeURIComponent(tgWebAppData);
            console.log('InitData from hash fragment (tgWebAppData):', initData);
        } else if (initDataFromUrl) {
            initData = initDataFromUrl;
            console.log('InitData from URL params:', initData);
        } else if (tgInitData) {
            initData = tgInitData;
            console.log('InitData from Telegram WebApp:', initData);
        } else {
            console.warn('No initData found in any source');
        }
        
        console.log('Final initData:', initData);
        console.log('Telegram WebApp object:', tg);
        
        // Игра Змейка
        class SnakeGame {
            constructor() {
                this.canvas = document.getElementById('gameCanvas');
                this.ctx = this.canvas.getContext('2d');
                this.gridSize = 15;
                this.tileCount = this.canvas.width / this.gridSize;
                
                this.snake = [
                    {x: 10, y: 10}
                ];
                this.food = {};
                this.dx = 0;
                this.dy = 0;
                this.score = 0;
                this.highScore = localStorage.getItem('snakeHighScore') || 0;
                this.gameRunning = false;
                
                this.generateFood();
                this.updateScore();
                this.setupTouchControls();
                this.draw();
            }
            
            generateFood() {
                this.food = {
                    x: Math.floor(Math.random() * this.tileCount),
                    y: Math.floor(Math.random() * this.tileCount)
                };
                
                // Проверяем, что еда не появилась на змее
                for (let segment of this.snake) {
                    if (segment.x === this.food.x && segment.y === this.food.y) {
                        this.generateFood();
                        break;
                    }
                }
            }
            
            draw() {
                // Очищаем canvas
                this.ctx.fillStyle = '#000';
                this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                
                // Рисуем змею
                this.ctx.fillStyle = '#0f0';
                for (let segment of this.snake) {
                    this.ctx.fillRect(segment.x * this.gridSize, segment.y * this.gridSize, this.gridSize - 2, this.gridSize - 2);
                }
                
                // Рисуем голову змеи другим цветом
                if (this.snake.length > 0) {
                    this.ctx.fillStyle = '#f0f';
                    const head = this.snake[0];
                    this.ctx.fillRect(head.x * this.gridSize, head.y * this.gridSize, this.gridSize - 2, this.gridSize - 2);
                }
                
                // Рисуем еду
                this.ctx.fillStyle = '#f00';
                this.ctx.fillRect(this.food.x * this.gridSize, this.food.y * this.gridSize, this.gridSize - 2, this.gridSize - 2);
            }
            
            update() {
                if (!this.gameRunning) return;
                
                const head = {x: this.snake[0].x + this.dx, y: this.snake[0].y + this.dy};
                
                // Проверка столкновения со стенами
                if (head.x < 0 || head.x >= this.tileCount || head.y < 0 || head.y >= this.tileCount) {
                    this.gameOver();
                    return;
                }
                
                // Проверка столкновения с собой
                for (let segment of this.snake) {
                    if (head.x === segment.x && head.y === segment.y) {
                        this.gameOver();
                        return;
                    }
                }
                
                this.snake.unshift(head);
                
                // Проверка поедания еды
                if (head.x === this.food.x && head.y === this.food.y) {
                    this.score += 10;
                    this.generateFood();
                    this.updateScore();
                    
                    // Вибрация при поедании еды
                    if (tg.HapticFeedback) {
                        tg.HapticFeedback.impactOccurred('light');
                    }
                } else {
                    this.snake.pop();
                }
                
                this.draw();
            }
            
            gameOver() {
                this.gameRunning = false;
                
                // Сохраняем результат на сервере
                this.saveScore();
                
                // Обновляем рекорд
                if (this.score > this.highScore) {
                    this.highScore = this.score;
                    localStorage.setItem('snakeHighScore', this.highScore);
                    
                    // Вибрация при новом рекорде
                    if (tg.HapticFeedback) {
                        tg.HapticFeedback.notificationOccurred('success');
                    }
                    
                    // Показываем уведомление о новом рекорде
                    tg.showAlert('🎉 Новый рекорд! Счёт: ' + this.score);
                } else {
                    if (tg.HapticFeedback) {
                        tg.HapticFeedback.notificationOccurred('error');
                    }
                    tg.showAlert('💀 Игра окончена! Счёт: ' + this.score);
                }
                
                this.updateScore();
                document.getElementById('playBtn').textContent = '▶️ ИГРАТЬ';
            }
            
            saveScore() {
                if (this.score > 0 && initData) {
                    fetch('/miniapp/save-score', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            initData: initData,
                            score: this.score,
                            high_score: this.highScore
                        })
                    }).then(response => response.json())
                    .then(data => {
                        console.log('Score saved:', data);
                    })
                    .catch(error => {
                        console.error('Error saving score:', error);
                    });
                }
            }
            
            start() {
                this.snake = [{x: 10, y: 10}];
                this.dx = 0;
                this.dy = 0;
                this.score = 0;
                this.gameRunning = true;
                this.generateFood();
                this.updateScore();
                this.draw();
                document.getElementById('playBtn').textContent = '⏸️ ПАУЗА';
            }
            
            pause() {
                this.gameRunning = false;
                document.getElementById('playBtn').textContent = '▶️ ПРОДОЛЖИТЬ';
            }
            
            resume() {
                this.gameRunning = true;
                document.getElementById('playBtn').textContent = '⏸️ ПАУЗА';
            }
            
            changeDirection(direction) {
                if (!this.gameRunning) return;
                
                const goingUp = this.dy === -1;
                const goingDown = this.dy === 1;
                const goingRight = this.dx === 1;
                const goingLeft = this.dx === -1;
                
                if (direction === 'up' && !goingDown) {
                    this.dx = 0;
                    this.dy = -1;
                }
                if (direction === 'down' && !goingUp) {
                    this.dx = 0;
                    this.dy = 1;
                }
                if (direction === 'left' && !goingRight) {
                    this.dx = -1;
                    this.dy = 0;
                }
                if (direction === 'right' && !goingLeft) {
                    this.dx = 1;
                    this.dy = 0;
                }
            }
            
            updateScore() {
                document.getElementById('score').textContent = this.score;
                document.getElementById('highScore').textContent = this.highScore;
            }
            
            setupTouchControls() {
                let startX, startY;
                
                this.canvas.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    const touch = e.touches[0];
                    startX = touch.clientX;
                    startY = touch.clientY;
                });
                
                this.canvas.addEventListener('touchend', (e) => {
                    e.preventDefault();
                    if (!startX || !startY) return;
                    
                    const touch = e.changedTouches[0];
                    const endX = touch.clientX;
                    const endY = touch.clientY;
                    
                    const diffX = startX - endX;
                    const diffY = startY - endY;
                    
                    if (Math.abs(diffX) > Math.abs(diffY)) {
                        // Горизонтальный свайп
                        if (diffX > 0) {
                            this.changeDirection('left');
                        } else {
                            this.changeDirection('right');
                        }
                    } else {
                        // Вертикальный свайп
                        if (diffY > 0) {
                            this.changeDirection('up');
                        } else {
                            this.changeDirection('down');
                        }
                    }
                    
                    startX = null;
                    startY = null;
                });
            }
        }
        
        // Инициализируем игру
        let game;
        
        function initGame() {
            game = new SnakeGame();
            
            // Игровой цикл
            setInterval(() => {
                game.update();
            }, 150);
        }
        
        function toggleGame() {
            if (!game.gameRunning) {
                if (game.score === 0 && game.snake.length === 1) {
                    game.start();
                } else {
                    game.resume();
                }
            } else {
                game.pause();
            }
        }
        
        function changeDirection(direction) {
            game.changeDirection(direction);
        }
        
        // Клавиатурное управление
        document.addEventListener('keydown', (e) => {
            if (game) {
                switch(e.key) {
                    case 'ArrowUp':
                        e.preventDefault();
                        game.changeDirection('up');
                        break;
                    case 'ArrowDown':
                        e.preventDefault();
                        game.changeDirection('down');
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        game.changeDirection('left');
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        game.changeDirection('right');
                        break;
                    case ' ':
                        e.preventDefault();
                        toggleGame();
                        break;
                }
            }
        });
        
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
                
                // Попробуем сначала обычный endpoint, затем debug
                let response;
                try {
                    response = await fetch('{{ route("miniapp.profile") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ initData: initData })
                    });
                } catch (error) {
                    console.log('Main endpoint failed, trying debug endpoint:', error);
                    response = await fetch('/miniapp/profile-debug', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ initData: initData })
                    });
                }
                
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
                        ${data.message ? '<div class="mt-2"><small class="text-info">' + data.message + '</small></div>' : ''}
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
                
                // Попробуем сначала обычный endpoint, затем debug
                let response;
                try {
                    response = await fetch('{{ route("miniapp.debug") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ initData: initData })
                    });
                } catch (error) {
                    console.log('Main debug endpoint failed, trying debug endpoint:', error);
                    response = await fetch('/miniapp/debug-debug', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ initData: initData })
                    });
                }
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Ошибка загрузки debug информации');
                }
                
                debugContent.innerHTML = `<div class="debug-info">${JSON.stringify(data.debug_info || data, null, 2)}</div>`;
                
            } catch (error) {
                console.error('Ошибка загрузки debug информации:', error);
                document.getElementById('debug-content').innerHTML = 
                    `<div class="alert alert-danger">Ошибка: ${error.message}</div>`;
            }
        }
        
        // Тестирование соединения
        async function testConnection() {
            try {
                const testContent = document.getElementById('test-content');
                testContent.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Тестируем...</div>';
                
                const response = await fetch('/miniapp/test-post', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ 
                        initData: initData,
                        test: 'connection',
                        timestamp: new Date().toISOString()
                    })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    testContent.innerHTML = `
                        <div class="alert alert-success">
                            <strong>✅ Соединение работает!</strong><br>
                            Статус: ${response.status}<br>
                            Время: ${data.timestamp}
                        </div>
                        <div class="debug-info">${JSON.stringify(data, null, 2)}</div>
                    `;
                } else {
                    throw new Error(`HTTP ${response.status}: ${data.error || 'Unknown error'}`);
                }
                
            } catch (error) {
                console.error('Ошибка тестирования соединения:', error);
                document.getElementById('test-content').innerHTML = 
                    `<div class="alert alert-danger">❌ Ошибка соединения: ${error.message}</div>`;
            }
        }
        
        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            showWebAppInfo();
            initGame(); // Инициализируем игру
            
            // Если есть initData, загружаем данные
            if (initData) {
                loadProfile();
            } else {
                document.getElementById('profile-content').innerHTML = 
                    '<div class="alert alert-warning">InitData отсутствует. Откройте приложение через Telegram.</div>';
            }
        });
        
        // Обработка событий Telegram WebApp
        tg.onEvent('mainButtonClicked', function() {
            // Отправляем текущий счёт в Telegram
            const currentScore = game ? game.score : 0;
            const highScore = game ? game.highScore : 0;
            tg.sendData(`score:${currentScore},highScore:${highScore}`);
        });
        
        tg.onEvent('backButtonClicked', function() {
            tg.close();
        });
        
        // Показать главную кнопку
        tg.MainButton.setText('Поделиться результатом');
        tg.MainButton.show();
        tg.MainButton.onClick(function() {
            const currentScore = game ? game.score : 0;
            const highScore = game ? game.highScore : 0;
            
            if (currentScore > 0 || highScore > 0) {
                tg.sendData(`snake_game_score:${currentScore},high_score:${highScore}`);
                tg.showAlert(`🐍 Результат: ${currentScore} очков\n🏆 Рекорд: ${highScore} очков`);
            } else {
                tg.showAlert('Начните игру, чтобы поделиться результатом!');
            }
        });
        
        // Включить подтверждение закрытия
        tg.enableClosingConfirmation();
    </script>
</body>
</html>
