<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Змейка | Telegram Mini App</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            touch-action: none;
        }
        
        body {
            background-color: var(--tg-theme-bg-color, #000000);
            color: var(--tg-theme-text-color, #ffffff);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            overscroll-behavior: contain;
            overflow: hidden;
            position: fixed;
            width: 100%;
            height: 100%;
        }
        
        #game-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        #game-canvas {
            width: 100%;
            height: 100%;
            display: block;
        }
        
        #score-display {
            position: absolute;
            top: env(safe-area-inset-top, 10px);
            left: 10px;
            padding: 5px 10px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border-radius: 12px;
            font-size: 18px;
            font-weight: bold;
            z-index: 100;
        }
        
        #game-over {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 200;
            color: white;
        }
        
        #game-over h2 {
            font-size: 32px;
            margin-bottom: 20px;
        }
        
        #game-over p {
            font-size: 24px;
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            background-color: var(--tg-theme-button-color, #2AABEE);
            color: var(--tg-theme-button-text-color, #ffffff);
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .controls-hint {
            position: absolute;
            bottom: env(safe-area-inset-bottom, 20px);
            left: 0;
            width: 100%;
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            pointer-events: none;
            opacity: 0.8;
            z-index: 100;
        }
        
        #debug-info {
            position: absolute;
            top: 50px;
            left: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            max-width: 300px;
            z-index: 150;
            display: none;
        }
    </style>
</head>
<body>
    <div id="game-container">
        <div id="score-display">Счёт: 0</div>
        <div id="debug-info"></div>
        <canvas id="game-canvas"></canvas>
        <div class="controls-hint">← Проведите для управления змейкой →</div>
        <div id="game-over">
            <h2>Игра окончена!</h2>
            <p>Ваш счёт: <span id="final-score">0</span></p>
            <button id="restart-btn" class="btn">Начать заново</button>
        </div>
    </div>

    <script>
        // Инициализация Telegram WebApp
        const tg = window.Telegram.WebApp;
        tg.expand();
        tg.ready();
        
        // Получаем цвета из темы Telegram
        const backgroundColor = tg.themeParams.bg_color || '#000000';
        const textColor = tg.themeParams.text_color || '#ffffff';
        const buttonColor = tg.themeParams.button_color || '#2AABEE';
        const buttonTextColor = tg.themeParams.button_text_color || '#ffffff';
        
        // Применяем цвета
        document.body.style.backgroundColor = backgroundColor;
        document.body.style.color = textColor;
        
        // Настройки игры
        const canvas = document.getElementById('game-canvas');
        const ctx = canvas.getContext('2d');
        const scoreDisplay = document.getElementById('score-display');
        const gameOverScreen = document.getElementById('game-over');
        const finalScoreDisplay = document.getElementById('final-score');
        const restartBtn = document.getElementById('restart-btn');
        const debugInfo = document.getElementById('debug-info');
        
        let score = 0;
        let gameOver = false;
        
        // Размер клетки игрового поля (увеличен на 30%)
        let cellSize = 0;
        // Количество клеток по горизонтали и вертикали (больше клеток для меньшего увеличения)
        const gridSize = { width: 20, height: 28 };
        
        // Аватарка пользователя
        let userAvatar = null;
        let userAvatarLoaded = false;
        let userProfileData = null;
        let avatarLoadAttempts = 0;
        
        // Направления змейки
        const directions = {
            UP: { x: 0, y: -1 },
            DOWN: { x: 0, y: 1 },
            LEFT: { x: -1, y: 0 },
            RIGHT: { x: 1, y: 0 }
        };
        
        // Состояние игры
        let snake = [];
        let food = null;
        let direction = directions.RIGHT;
        let nextDirection = direction;
        let gameSpeed = 150;
        let lastUpdateTime = 0;
        
        // Функция отладки
        function debugLog(message) {
            console.log('[Snake Game Debug]', message);
            const debugDiv = document.getElementById('debug-info');
            if (debugDiv) {
                debugDiv.innerHTML += message + '<br>';
                debugDiv.style.display = 'block';
            }
        }
        
        // Получение фото пользователя через Bot API
        async function getUserPhotoFromAPI(userId) {
            try {
                debugLog(`🔄 Запрашиваем фото через Bot API для user_id: ${userId}`);
                
                const response = await fetch(`/miniapp/user-photo/${userId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                debugLog(`📡 Bot API ответ: статус ${response.status}`);
                
                if (response.ok) {
                    const data = await response.json();
                    debugLog(`📊 Bot API данные:`, data);
                    
                    if (data.success && data.photo_url) {
                        debugLog(`✅ Получен photo_url: ${data.photo_url}`);
                        return data.photo_url;
                    } else {
                        debugLog(`❌ Bot API вернул ошибку: ${data.error || 'неизвестная ошибка'}`);
                        if (data.telegram_error) {
                            debugLog(`🔍 Детали ошибки Telegram: ${JSON.stringify(data.telegram_error)}`);
                        }
                    }
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    debugLog(`❌ Bot API HTTP ошибка: ${response.status}`);
                    debugLog(`🔍 Детали ошибки: ${JSON.stringify(errorData)}`);
                }
            } catch (e) {
                debugLog(`❌ Bot API исключение: ${e.message}`);
            }
            return null;
        }
        
        // Улучшенное создание fallback аватарки
        function createFallbackAvatar() {
            debugLog('🎨 Создаем улучшенную fallback аватарку');
            const avatarCanvas = document.createElement('canvas');
            avatarCanvas.width = 256;
            avatarCanvas.height = 256;
            const avatarCtx = avatarCanvas.getContext('2d');
            
            // Получаем данные пользователя
            let initial = '?';
            let userName = 'Unknown';
            let userColor = '#2AABEE';
            
            if (userProfileData && userProfileData.user) {
                const user = userProfileData.user;
                userName = user.first_name || user.username || 'User';
                initial = userName[0].toUpperCase();
                // Генерируем цвет на основе ID пользователя
                if (user.id) {
                    const colors = ['#2AABEE', '#229ED9', '#1E88E5', '#3F51B5', '#673AB7', '#9C27B0', '#E91E63', '#F44336', '#FF5722', '#FF9800'];
                    userColor = colors[user.id % colors.length];
                }
            } else if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
                const user = tg.initDataUnsafe.user;
                userName = user.first_name || user.username || 'User';
                initial = userName[0].toUpperCase();
                if (user.id) {
                    const colors = ['#2AABEE', '#229ED9', '#1E88E5', '#3F51B5', '#673AB7', '#9C27B0', '#E91E63', '#F44336', '#FF5722', '#FF9800'];
                    userColor = colors[user.id % colors.length];
                }
            }
            
            debugLog(`👤 Fallback для: ${userName}, инициал: ${initial}, цвет: ${userColor}`);
            
            // Рисуем круглый градиентный фон
            const gradient = avatarCtx.createRadialGradient(128, 128, 0, 128, 128, 128);
            gradient.addColorStop(0, userColor);
            gradient.addColorStop(1, adjustBrightness(userColor, -20));
            avatarCtx.fillStyle = gradient;
            avatarCtx.fillRect(0, 0, 256, 256);
            
            // Добавляем тонкий градиентный оверлей
            const overlayGradient = avatarCtx.createLinearGradient(0, 0, 256, 256);
            overlayGradient.addColorStop(0, 'rgba(255,255,255,0.1)');
            overlayGradient.addColorStop(1, 'rgba(0,0,0,0.1)');
            avatarCtx.fillStyle = overlayGradient;
            avatarCtx.fillRect(0, 0, 256, 256);
            
            // Рисуем инициал с тенью
            avatarCtx.fillStyle = '#ffffff';
            avatarCtx.font = 'bold 120px -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif';
            avatarCtx.textAlign = 'center';
            avatarCtx.textBaseline = 'middle';
            
            // Добавляем тень
            avatarCtx.shadowColor = 'rgba(0,0,0,0.3)';
            avatarCtx.shadowBlur = 8;
            avatarCtx.shadowOffsetX = 2;
            avatarCtx.shadowOffsetY = 2;
            
            avatarCtx.fillText(initial, 128, 128);
            
            // Убираем тень для следующих операций
            avatarCtx.shadowColor = 'transparent';
            avatarCtx.shadowBlur = 0;
            avatarCtx.shadowOffsetX = 0;
            avatarCtx.shadowOffsetY = 0;
            
            userAvatar = avatarCanvas;
            userAvatarLoaded = true;
            debugLog('✅ Улучшенная fallback аватарка создана');
        }
        
        // Функция для изменения яркости цвета
        function adjustBrightness(hex, percent) {
            // Убираем # если есть
            hex = hex.replace('#', '');
            
            // Конвертируем в RGB
            const r = parseInt(hex.substr(0, 2), 16);
            const g = parseInt(hex.substr(2, 2), 16);
            const b = parseInt(hex.substr(4, 2), 16);
            
            // Применяем изменение яркости
            const newR = Math.max(0, Math.min(255, r + (r * percent / 100)));
            const newG = Math.max(0, Math.min(255, g + (g * percent / 100)));
            const newB = Math.max(0, Math.min(255, b + (b * percent / 100)));
            
            // Конвертируем обратно в hex
            return `#${Math.round(newR).toString(16).padStart(2, '0')}${Math.round(newG).toString(16).padStart(2, '0')}${Math.round(newB).toString(16).padStart(2, '0')}`;
        }
        
        // Тест прямой загрузки фото по URL (как последний резерв)
        async function testDirectPhotoLoad(photoUrl) {
            return new Promise((resolve) => {
                debugLog(`🧪 Тестируем прямую загрузку: ${photoUrl}`);
                
                const img = new Image();
                img.crossOrigin = 'anonymous';
                
                const timeout = setTimeout(() => {
                    debugLog('⏰ Таймаут загрузки фото');
                    resolve(false);
                }, 10000); // 10 секунд таймаут
                
                img.onload = function() {
                    clearTimeout(timeout);
                    debugLog(`✅ Фото загружено напрямую: ${img.width}x${img.height}`);
                    userAvatar = img;
                    userAvatarLoaded = true;
                    resolve(true);
                };
                
                img.onerror = function() {
                    clearTimeout(timeout);
                    debugLog('❌ Прямая загрузка не удалась');
                    resolve(false);
                };
                
                img.src = photoUrl;
            });
        }
        
        // Загрузка реальной аватарки пользователя (улучшенная версия)
        async function loadUserAvatar() {
            debugLog('🔄 Начинаем загрузку аватарки пользователя...');
            avatarLoadAttempts++;
            
            // Сначала пытаемся получить данные пользователя через API
            await loadUserProfileData();
            
            // Пытаемся загрузить фото из разных источников
            let photoUrl = null;
            let photoSource = 'none';
            
            // 1. Пытаемся получить через Telegram Bot API (приоритет)
            if (userProfileData && userProfileData.user && userProfileData.user.id) {
                try {
                    debugLog(`🔄 Пытаемся получить фото через Bot API для user_id: ${userProfileData.user.id}`);
                    photoUrl = await getUserPhotoFromAPI(userProfileData.user.id);
                    if (photoUrl) {
                        photoSource = 'Bot API';
                        debugLog(`✅ Получено фото через Bot API: ${photoUrl}`);
                        
                        // Пытаемся загрузить полученное фото
                        const success = await testDirectPhotoLoad(photoUrl);
                        if (success) {
                            setTimeout(() => {
                                debugInfo.style.display = 'none';
                            }, 3000);
                            return;
                        }
                    }
                } catch (e) {
                    debugLog(`❌ Ошибка Bot API: ${e.message}`);
                }
            }
            
            // 2. Пытаемся загрузить из других источников (на всякий случай)
            const sources = [
                { url: userProfileData?.user?.photo_url, name: 'server profile' },
                { url: tg.initDataUnsafe?.user?.photo_url, name: 'initDataUnsafe' }
            ];
            
            for (const source of sources) {
                if (source.url && !source.url.includes('t.me/i/userpic')) {
                    debugLog(`🔄 Пробуем загрузить из ${source.name}: ${source.url}`);
                    const success = await testDirectPhotoLoad(source.url);
                    if (success) {
                        photoSource = source.name;
                        debugLog(`✅ Фото загружено из ${source.name}`);
                        setTimeout(() => {
                            debugInfo.style.display = 'none';
                        }, 3000);
                        return;
                    }
                } else if (source.url) {
                    debugLog(`❌ Пропускаем ${source.name} URL: ${source.url}`);
                }
            }
            
            debugLog('ℹ️ Рабочего URL фото не найдено, создаем fallback аватарку');
            createFallbackAvatar();
            
            // Скрываем отладочную информацию через 3 секунды
            setTimeout(() => {
                debugInfo.style.display = 'none';
            }, 3000);
        }
        
        // Загрузка данных профиля пользователя
        async function loadUserProfileData() {
            try {
                const initData = getInitData();
                debugLog(`🔄 InitData длина: ${initData ? initData.length : 0}`);
                
                if (!initData) {
                    debugLog('❌ InitData недоступна');
                    return;
                }
                
                debugLog('🔄 Отправляем запрос к /miniapp/profile-debug');
                const response = await fetch('/miniapp/profile-debug', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ initData: initData })
                });
                
                if (response.ok) {
                    userProfileData = await response.json();
                    debugLog(`✅ Данные профиля загружены`);
                    if (userProfileData.user) {
                        debugLog(`👤 Пользователь: ${userProfileData.user.first_name || 'Unknown'} (ID: ${userProfileData.user.id || 'Unknown'})`);
                        if (userProfileData.user.photo_url) {
                            debugLog(`📸 Photo URL в данных: ${userProfileData.user.photo_url}`);
                        } else {
                            debugLog('📸 Photo URL отсутствует в данных профиля');
                        }
                    }
                } else {
                    debugLog(`❌ Ошибка загрузки профиля: ${response.status}`);
                }
            } catch (error) {
                debugLog(`❌ Исключение при загрузке профиля: ${error.message}`);
            }
        }
        
        // Получение initData из различных источников
        function getInitData() {
            const urlParams = new URLSearchParams(window.location.search);
            const hashParams = new URLSearchParams(window.location.hash.substring(1));
            
            const sources = [
                hashParams.get('tgWebAppData') ? decodeURIComponent(hashParams.get('tgWebAppData')) : null,
                urlParams.get('initData'),
                tg.initData
            ];
            
            return sources.find(source => source && source.length > 0) || '';
        }
        
        // Создание fallback аватарки
        function createFallbackAvatar() {
            debugLog('🎨 Создаем fallback аватарку');
            const avatarCanvas = document.createElement('canvas');
            avatarCanvas.width = 128;
            avatarCanvas.height = 128;
            const avatarCtx = avatarCanvas.getContext('2d');
            
            // Рисуем градиентный фон
            const gradient = avatarCtx.createLinearGradient(0, 0, 128, 128);
            gradient.addColorStop(0, '#2AABEE');
            gradient.addColorStop(0.5, '#229ED9');
            gradient.addColorStop(1, '#1E88E5');
            avatarCtx.fillStyle = gradient;
            avatarCtx.fillRect(0, 0, 128, 128);
            
            // Получаем первую букву имени пользователя
            let initial = '?';
            let userName = 'Unknown';
            
            if (userProfileData && userProfileData.user) {
                const user = userProfileData.user;
                userName = user.first_name || user.username || 'User';
                initial = userName[0].toUpperCase();
            } else if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
                const user = tg.initDataUnsafe.user;
                userName = user.first_name || user.username || 'User';
                initial = userName[0].toUpperCase();
            }
            
            debugLog(`👤 Fallback для пользователя: ${userName}, инициал: ${initial}`);
            
            // Рисуем инициал
            avatarCtx.fillStyle = '#ffffff';
            avatarCtx.font = 'bold 64px Arial';
            avatarCtx.textAlign = 'center';
            avatarCtx.textBaseline = 'middle';
            avatarCtx.shadowColor = 'rgba(0,0,0,0.3)';
            avatarCtx.shadowBlur = 4;
            avatarCtx.fillText(initial, 64, 64);
            
            userAvatar = avatarCanvas;
            userAvatarLoaded = true;
            debugLog('✅ Fallback аватарка создана');
        }
        
        // Инициализация игры
        function initGame() {
            resizeCanvas();
            loadUserAvatar();
            
            // Создаем змейку в центре поля
            const centerX = Math.floor(gridSize.width / 2);
            const centerY = Math.floor(gridSize.height / 2);
            snake = [
                { x: centerX, y: centerY },
                { x: centerX - 1, y: centerY },
                { x: centerX - 2, y: centerY }
            ];
            
            // Ставим еду
            placeFood();
            
            // Сбрасываем направление и счет
            direction = directions.RIGHT;
            nextDirection = direction;
            score = 0;
            gameOver = false;
            gameSpeed = 150;
            
            // Обновляем счёт
            updateScore();
            
            // Скрываем экран окончания игры
            gameOverScreen.style.display = 'none';
            
            // Запускаем игровой цикл
            requestAnimationFrame(gameLoop);
        }
        
        // Изменение размера холста при изменении размера окна
        function resizeCanvas() {
            const width = window.innerWidth;
            const height = window.innerHeight;
            
            canvas.width = width;
            canvas.height = height;
            
            // Увеличиваем размер клетки на 30%
            cellSize = Math.min(
                Math.floor(width / gridSize.width),
                Math.floor(height / gridSize.height)
            ) * 1.3; // Увеличиваем размер клетки на 30%
        }
        
        // Размещаем еду в случайном месте
        function placeFood() {
            const x = Math.floor(Math.random() * gridSize.width);
            const y = Math.floor(Math.random() * gridSize.height);
            
            const isOnSnake = snake.some(segment => segment.x === x && segment.y === y);
            
            if (isOnSnake) {
                placeFood();
            } else {
                food = { x, y };
            }
        }
        
        // Обновляем положение змейки
        function update(timestamp) {
            if (gameOver) return;
            
            if (timestamp - lastUpdateTime < gameSpeed) return;
            lastUpdateTime = timestamp;
            
            direction = nextDirection;
            
            const head = { ...snake[0] };
            
            head.x += direction.x;
            head.y += direction.y;
            
            // Телепортация через границы
            if (head.x < 0) head.x = gridSize.width - 1;
            if (head.x >= gridSize.width) head.x = 0;
            if (head.y < 0) head.y = gridSize.height - 1;
            if (head.y >= gridSize.height) head.y = 0;
            
            // Проверяем столкновения с самой собой
            if (snake.some(segment => segment.x === head.x && segment.y === head.y)) {
                endGame();
                return;
            }
            
            snake.unshift(head);
            
            // Проверяем съедание еды
            if (head.x === food.x && head.y === food.y) {
                score += 10;
                updateScore();
                placeFood();
                
                if (gameSpeed > 50) {
                    gameSpeed -= 2;
                }
            } else {
                snake.pop();
            }
        }
        
        // Отрисовка игры с эффектом хлыста и черной змейкой
        function draw() {
            ctx.fillStyle = backgroundColor;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            const offsetX = (canvas.width - cellSize * gridSize.width) / 2;
            const offsetY = (canvas.height - cellSize * gridSize.height) / 2;
            
            // Рисуем еду с пульсацией (увеличенную на 30%)
            const pulse = Math.sin(Date.now() / 200) * 0.12 + 0.88;
            ctx.fillStyle = '#FF4136';
            ctx.shadowColor = '#FF4136';
            ctx.shadowBlur = 8;
            drawRoundedRect(
                offsetX + food.x * cellSize + cellSize * (1 - pulse) / 2, 
                offsetY + food.y * cellSize + cellSize * (1 - pulse) / 2, 
                cellSize * pulse, 
                cellSize * pulse, 
                cellSize / 5 * pulse
            );
            ctx.shadowBlur = 0;
            
            // Рисуем змейку с эффектом хлыста (черная)
            snake.forEach((segment, index) => {
                const x = offsetX + segment.x * cellSize;
                const y = offsetY + segment.y * cellSize;
                
                // Вычисляем размер сегмента (эффект хлыста)
                const maxSegments = Math.min(snake.length, 10);
                const segmentProgress = Math.min(index / maxSegments, 1);
                const sizeMultiplier = 1 - (segmentProgress * 0.4); // уменьшение до 60%
                const actualSize = cellSize * sizeMultiplier;
                const offset = (cellSize - actualSize) / 2;
                
                if (index === 0) {
                    // Голова змейки с реальной аватаркой
                    if (userAvatarLoaded && userAvatar) {
                        ctx.save();
                        
                        // Создаем круглую маску
                        ctx.beginPath();
                        ctx.arc(x + cellSize/2, y + cellSize/2, actualSize/2 - 3, 0, 2 * Math.PI);
                        ctx.clip();
                        
                        // Рисуем аватарку
                        ctx.drawImage(
                            userAvatar, 
                            x + offset + 3, 
                            y + offset + 3, 
                            actualSize - 6, 
                            actualSize - 6
                        );
                        
                        ctx.restore();
                        
                        // Добавляем контур для головы (золотой)
                        ctx.strokeStyle = '#FFD700';
                        ctx.lineWidth = 3;
                        ctx.shadowColor = '#FFD700';
                        ctx.shadowBlur = 6;
                        ctx.beginPath();
                        ctx.arc(x + cellSize/2, y + cellSize/2, actualSize/2 - 2, 0, 2 * Math.PI);
                        ctx.stroke();
                        ctx.shadowBlur = 0;
                    } else {
                        // Fallback для головы (черный с золотым контуром)
                        ctx.fillStyle = '#1a1a1a';
                        drawRoundedRect(x + offset, y + offset, actualSize, actualSize, actualSize / 5);
                        
                        ctx.strokeStyle = '#FFD700';
                        ctx.lineWidth = 2;
                        ctx.shadowColor = '#FFD700';
                        ctx.shadowBlur = 4;
                        ctx.beginPath();
                        ctx.roundRect(x + offset, y + offset, actualSize, actualSize, actualSize / 5);
                        ctx.stroke();
                        ctx.shadowBlur = 0;
                    }
                } else {
                    // Тело змейки (черное с эффектом хлыста)
                    const alpha = 1 - (segmentProgress * 0.3);
                    
                    // Основной цвет тела (черный)
                    ctx.fillStyle = `rgba(26, 26, 26, ${alpha})`;
                    drawRoundedRect(x + offset, y + offset, actualSize, actualSize, actualSize / 5);
                    
                    // Добавляем внутреннее свечение (темно-серый)
                    if (index < 5) {
                        ctx.fillStyle = `rgba(64, 64, 64, ${alpha * 0.4})`;
                        const innerSize = actualSize * 0.75;
                        const innerOffset = (actualSize - innerSize) / 2;
                        drawRoundedRect(
                            x + offset + innerOffset, 
                            y + offset + innerOffset, 
                            innerSize, 
                            innerSize, 
                            innerSize / 5
                        );
                    }
                    
                    // Добавляем тонкий контур
                    if (index < 4) {
                        ctx.strokeStyle = `rgba(90, 90, 90, ${alpha * 0.6})`;
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.roundRect(x + offset, y + offset, actualSize, actualSize, actualSize / 5);
                        ctx.stroke();
                    }
                }
            });
        }
        
        // Вспомогательная функция для рисования скругленных прямоугольников
        function drawRoundedRect(x, y, width, height, radius) {
            ctx.beginPath();
            ctx.moveTo(x + radius, y);
            ctx.lineTo(x + width - radius, y);
            ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
            ctx.lineTo(x + width, y + height - radius);
            ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
            ctx.lineTo(x + radius, y + height);
            ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
            ctx.lineTo(x, y + radius);
            ctx.quadraticCurveTo(x, y, x + radius, y);
            ctx.closePath();
            ctx.fill();
        }
        
        // Основной игровой цикл
        function gameLoop(timestamp) {
            update(timestamp);
            draw();
            requestAnimationFrame(gameLoop);
        }
        
        // Обновляем отображение счета
        function updateScore() {
            scoreDisplay.textContent = `Счёт: ${score}`;
        }
        
        // Обработка окончания игры
        function endGame() {
            gameOver = true;
            finalScoreDisplay.textContent = score;
            gameOverScreen.style.display = 'flex';
            
            if ('vibrate' in navigator) {
                navigator.vibrate(200);
            }
        }
        
        // Обработка нажатий клавиш (для отладки на ПК)
        document.addEventListener('keydown', (e) => {
            switch (e.key) {
                case 'ArrowUp':
                    if (direction !== directions.DOWN) nextDirection = directions.UP;
                    break;
                case 'ArrowDown':
                    if (direction !== directions.UP) nextDirection = directions.DOWN;
                    break;
                case 'ArrowLeft':
                    if (direction !== directions.RIGHT) nextDirection = directions.LEFT;
                    break;
                case 'ArrowRight':
                    if (direction !== directions.LEFT) nextDirection = directions.RIGHT;
                    break;
                case 'KeyD':
                    // Переключение отладочной информации
                    debugInfo.style.display = debugInfo.style.display === 'none' ? 'block' : 'none';
                    break;
            }
        });
        
        // Обработка свайпов для мобильных устройств
        let touchStartX = 0;
        let touchStartY = 0;
        
        document.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });
        
        document.addEventListener('touchmove', (e) => {
            if (gameOver) return;
            e.preventDefault();
        }, { passive: false });
        
        document.addEventListener('touchend', (e) => {
            if (gameOver) return;
            
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            
            const diffX = touchEndX - touchStartX;
            const diffY = touchEndY - touchStartY;
            
            if (Math.abs(diffX) > Math.abs(diffY)) {
                if (diffX > 30) {
                    if (direction !== directions.LEFT) nextDirection = directions.RIGHT;
                } else if (diffX < -30) {
                    if (direction !== directions.RIGHT) nextDirection = directions.LEFT;
                }
            } else {
                if (diffY > 30) {
                    if (direction !== directions.UP) nextDirection = directions.DOWN;
                } else if (diffY < -30) {
                    if (direction !== directions.DOWN) nextDirection = directions.UP;
                }
            }
        }, { passive: true });
        
        // Обработчик кнопки перезапуска
        restartBtn.addEventListener('click', () => {
            initGame();
        });
        
        // Обработчик изменения размера окна
        window.addEventListener('resize', () => {
            resizeCanvas();
        });
        
        // Запускаем игру при загрузке страницы
        window.addEventListener('load', () => {
            initGame();
        });
    </script>
</body>
</html>
