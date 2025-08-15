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
    </style>
</head>
<body>
    <div id="game-container">
        <div id="score-display">Счёт: 0</div>
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
        
        let score = 0;
        let gameOver = false;
        
        // Размер клетки игрового поля
        let cellSize = 0;
        // Количество клеток по горизонтали и вертикали (без границ)
        const gridSize = { width: 25, height: 35 };
        
        // Аватарка пользователя
        let userAvatar = null;
        let userAvatarLoaded = false;
        let userProfileData = null;
        
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
        let gameSpeed = 150; // начальная скорость змейки в мс
        let lastUpdateTime = 0;
        
        // Загрузка реальной аватарки пользователя
        async function loadUserAvatar() {
            console.log('Loading user avatar...');
            
            // Сначала пытаемся получить данные пользователя через API
            await loadUserProfileData();
            
            // Пытаемся загрузить фото из разных источников
            let photoUrl = null;
            
            // 1. Из данных профиля с сервера
            if (userProfileData && userProfileData.user && userProfileData.user.photo_url) {
                photoUrl = userProfileData.user.photo_url;
                console.log('Found photo URL from server:', photoUrl);
            }
            
            // 2. Из initDataUnsafe Telegram WebApp
            if (!photoUrl && tg.initDataUnsafe && tg.initDataUnsafe.user && tg.initDataUnsafe.user.photo_url) {
                photoUrl = tg.initDataUnsafe.user.photo_url;
                console.log('Found photo URL from initDataUnsafe:', photoUrl);
            }
            
            // 3. Пытаемся получить через Telegram Bot API (если есть username)
            if (!photoUrl && userProfileData && userProfileData.user && userProfileData.user.username) {
                try {
                    photoUrl = await getUserPhotoFromAPI(userProfileData.user.id);
                    console.log('Found photo URL from Bot API:', photoUrl);
                } catch (e) {
                    console.log('Failed to get photo from Bot API:', e.message);
                }
            }
            
            if (photoUrl) {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = function() {
                    userAvatar = img;
                    userAvatarLoaded = true;
                    console.log('User avatar loaded successfully');
                };
                img.onerror = function() {
                    console.log('Failed to load user avatar, using fallback');
                    createFallbackAvatar();
                };
                img.src = photoUrl;
            } else {
                console.log('No photo URL found, using fallback');
                createFallbackAvatar();
            }
        }
        
        // Загрузка данных профиля пользователя
        async function loadUserProfileData() {
            try {
                const initData = getInitData();
                if (!initData) {
                    console.log('No initData available');
                    return;
                }
                
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
                    console.log('User profile data loaded:', userProfileData);
                } else {
                    console.log('Failed to load user profile data:', response.status);
                }
            } catch (error) {
                console.log('Error loading user profile data:', error);
            }
        }
        
        // Получение initData из различных источников
        function getInitData() {
            const urlParams = new URLSearchParams(window.location.search);
            const hashParams = new URLSearchParams(window.location.hash.substring(1));
            
            return hashParams.get('tgWebAppData') ? decodeURIComponent(hashParams.get('tgWebAppData')) :
                   urlParams.get('initData') || 
                   tg.initData || 
                   '';
        }
        
        // Получение фото пользователя через Bot API (экспериментальная функция)
        async function getUserPhotoFromAPI(userId) {
            try {
                const response = await fetch(`/miniapp/user-photo/${userId}`);
                if (response.ok) {
                    const data = await response.json();
                    return data.photo_url;
                }
            } catch (e) {
                console.log('Bot API photo fetch failed:', e);
            }
            return null;
        }
        
        // Создание fallback аватарки
        function createFallbackAvatar() {
            const avatarCanvas = document.createElement('canvas');
            avatarCanvas.width = 64;
            avatarCanvas.height = 64;
            const avatarCtx = avatarCanvas.getContext('2d');
            
            // Рисуем градиентный фон
            const gradient = avatarCtx.createLinearGradient(0, 0, 64, 64);
            gradient.addColorStop(0, '#2AABEE');
            gradient.addColorStop(1, '#229ED9');
            avatarCtx.fillStyle = gradient;
            avatarCtx.fillRect(0, 0, 64, 64);
            
            // Получаем первую букву имени пользователя
            let initial = '?';
            if (userProfileData && userProfileData.user) {
                const user = userProfileData.user;
                initial = (user.first_name || user.username || '?')[0].toUpperCase();
            } else if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
                const user = tg.initDataUnsafe.user;
                initial = (user.first_name || user.username || '?')[0].toUpperCase();
            }
            
            // Рисуем инициал
            avatarCtx.fillStyle = '#ffffff';
            avatarCtx.font = 'bold 32px Arial';
            avatarCtx.textAlign = 'center';
            avatarCtx.textBaseline = 'middle';
            avatarCtx.fillText(initial, 32, 32);
            
            userAvatar = avatarCanvas;
            userAvatarLoaded = true;
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
            
            cellSize = Math.min(
                Math.floor(width / gridSize.width),
                Math.floor(height / gridSize.height)
            );
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
        
        // Отрисовка игры с эффектом хлыста
        function draw() {
            ctx.fillStyle = backgroundColor;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            const offsetX = (canvas.width - cellSize * gridSize.width) / 2;
            const offsetY = (canvas.height - cellSize * gridSize.height) / 2;
            
            // Рисуем еду с пульсацией
            const pulse = Math.sin(Date.now() / 200) * 0.1 + 0.9;
            ctx.fillStyle = '#FF4136';
            drawRoundedRect(
                offsetX + food.x * cellSize + cellSize * (1 - pulse) / 2, 
                offsetY + food.y * cellSize + cellSize * (1 - pulse) / 2, 
                cellSize * pulse, 
                cellSize * pulse, 
                cellSize / 3 * pulse
            );
            
            // Рисуем змейку с эффектом хлыста
            snake.forEach((segment, index) => {
                const x = offsetX + segment.x * cellSize;
                const y = offsetY + segment.y * cellSize;
                
                // Вычисляем размер сегмента (эффект хлыста)
                const maxSegments = Math.min(snake.length, 15); // максимум 15 сегментов для расчета
                const segmentProgress = Math.min(index / maxSegments, 1);
                const sizeMultiplier = 1 - (segmentProgress * 0.4); // уменьшение до 60% от оригинального размера
                const actualSize = cellSize * sizeMultiplier;
                const offset = (cellSize - actualSize) / 2;
                
                if (index === 0) {
                    // Голова змейки с реальной аватаркой
                    if (userAvatarLoaded && userAvatar) {
                        ctx.save();
                        
                        // Создаем круглую маску
                        ctx.beginPath();
                        ctx.arc(x + cellSize/2, y + cellSize/2, actualSize/2 - 2, 0, 2 * Math.PI);
                        ctx.clip();
                        
                        // Рисуем аватарку
                        ctx.drawImage(
                            userAvatar, 
                            x + offset + 2, 
                            y + offset + 2, 
                            actualSize - 4, 
                            actualSize - 4
                        );
                        
                        ctx.restore();
                        
                        // Добавляем контур для головы
                        ctx.strokeStyle = '#2ECC40';
                        ctx.lineWidth = 3;
                        ctx.beginPath();
                        ctx.arc(x + cellSize/2, y + cellSize/2, actualSize/2 - 1, 0, 2 * Math.PI);
                        ctx.stroke();
                    } else {
                        // Fallback для головы
                        ctx.fillStyle = '#2ECC40';
                        drawRoundedRect(x + offset, y + offset, actualSize, actualSize, actualSize / 3);
                    }
                } else {
                    // Тело змейки с градиентом и эффектом хлыста
                    const alpha = 1 - (segmentProgress * 0.3); // постепенно делаем прозрачнее
                    
                    // Основной цвет тела
                    ctx.fillStyle = `rgba(1, 255, 112, ${alpha})`;
                    drawRoundedRect(x + offset, y + offset, actualSize, actualSize, actualSize / 3);
                    
                    // Добавляем внутреннее свечение для красоты
                    if (index < 5) {
                        ctx.fillStyle = `rgba(46, 204, 64, ${alpha * 0.3})`;
                        const innerSize = actualSize * 0.6;
                        const innerOffset = (actualSize - innerSize) / 2;
                        drawRoundedRect(
                            x + offset + innerOffset, 
                            y + offset + innerOffset, 
                            innerSize, 
                            innerSize, 
                            innerSize / 3
                        );
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
            
            // Определяем направление свайпа
            if (Math.abs(diffX) > Math.abs(diffY)) {
                // Горизонтальный свайп
                if (diffX > 30) {
                    // Свайп вправо
                    if (direction !== directions.LEFT) nextDirection = directions.RIGHT;
                } else if (diffX < -30) {
                    // Свайп влево
                    if (direction !== directions.RIGHT) nextDirection = directions.LEFT;
                }
            } else {
                // Вертикальный свайп
                if (diffY > 30) {
                    // Свайп вниз
                    if (direction !== directions.UP) nextDirection = directions.DOWN;
                } else if (diffY < -30) {
                    // Свайп вверх
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
