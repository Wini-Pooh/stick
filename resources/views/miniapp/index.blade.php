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
        
        // Направления змейки
        const directions = {
            UP: { x: 0, y: -1 },
            DOWN: { x: 0, y: 1 },
            LEFT: { x: -1, y: 0 },
            RIGHT: { x: 1, y: 0 }
        };
        
        // Состояние игры для плавного движения
        let snake = [];
        let renderSnake = []; // Для плавной анимации
        let food = [];
        let direction = directions.RIGHT;
        let nextDirection = direction;
        let gameSpeed = 180; // увеличена начальная скорость для плавности
        let lastUpdateTime = 0;
        let animationProgress = 0; // Прогресс анимации между кадрами
        
        // Загрузка аватарки пользователя через Telegram Bot API
        async function loadUserAvatar() {
            console.log('Loading user avatar...');
            console.log('Telegram WebApp data:', tg.initDataUnsafe);
            
            let userPhotoLoaded = false;
            
            // Пробуем загрузить фото через Bot API
            if (tg.initDataUnsafe && tg.initDataUnsafe.user && tg.initDataUnsafe.user.id) {
                try {
                    const userId = tg.initDataUnsafe.user.id;
                    console.log('Trying to load avatar for user ID:', userId);
                    
                    // Отправляем запрос на сервер для получения фото профиля
                    const response = await fetch('/miniapp/get-user-photo', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            user_id: userId,
                            initData: tg.initData
                        })
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success && data.photo_url) {
                            console.log('Got photo URL from server:', data.photo_url);
                            
                            const img = new Image();
                            img.crossOrigin = 'anonymous';
                            img.onload = function() {
                                userAvatar = img;
                                userAvatarLoaded = true;
                                userPhotoLoaded = true;
                                console.log('User avatar loaded successfully from Bot API');
                            };
                            img.onerror = function() {
                                console.log('Failed to load user avatar from Bot API, trying fallback');
                                tryDirectPhotoUrl();
                            };
                            img.src = data.photo_url;
                        } else {
                            console.log('No photo URL from server, trying direct method');
                            tryDirectPhotoUrl();
                        }
                    } else {
                        console.log('Server request failed, trying direct method');
                        tryDirectPhotoUrl();
                    }
                } catch (error) {
                    console.log('Error loading avatar from server:', error);
                    tryDirectPhotoUrl();
                }
            } else {
                console.log('No user ID found, creating fallback avatar');
                createFallbackAvatar();
            }
            
            // Fallback - пытаемся использовать прямой URL если есть
            function tryDirectPhotoUrl() {
                if (userPhotoLoaded) return; // Уже загружено
                
                if (tg.initDataUnsafe && tg.initDataUnsafe.user && tg.initDataUnsafe.user.photo_url) {
                    const img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.onload = function() {
                        userAvatar = img;
                        userAvatarLoaded = true;
                        console.log('User avatar loaded from direct URL');
                    };
                    img.onerror = function() {
                        console.log('Failed to load from direct URL, creating fallback');
                        createFallbackAvatar();
                    };
                    img.src = tg.initDataUnsafe.user.photo_url;
                } else {
                    createFallbackAvatar();
                }
            }
            
            // Таймаут на случай если запрос слишком долгий
            setTimeout(() => {
                if (!userAvatarLoaded) {
                    console.log('Avatar loading timeout, creating fallback');
                    createFallbackAvatar();
                }
            }, 3000);
        }
        
        // Создание fallback аватарки
        function createFallbackAvatar() {
            if (userAvatarLoaded) return; // Уже загружено
            
            const avatarCanvas = document.createElement('canvas');
            avatarCanvas.width = 128;
            avatarCanvas.height = 128;
            const avatarCtx = avatarCanvas.getContext('2d');
            
            // Создаем градиентный фон
            const gradient = avatarCtx.createLinearGradient(0, 0, 128, 128);
            gradient.addColorStop(0, '#2AABEE');
            gradient.addColorStop(1, '#1e90ff');
            
            avatarCtx.fillStyle = gradient;
            avatarCtx.fillRect(0, 0, 128, 128);
            
            // Получаем первую букву имени пользователя
            let initial = '?';
            if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
                const user = tg.initDataUnsafe.user;
                initial = (user.first_name || user.username || '?')[0].toUpperCase();
            }
            
            // Рисуем букву с тенью
            avatarCtx.shadowColor = 'rgba(0, 0, 0, 0.3)';
            avatarCtx.shadowBlur = 4;
            avatarCtx.shadowOffsetX = 2;
            avatarCtx.shadowOffsetY = 2;
            
            avatarCtx.fillStyle = '#ffffff';
            avatarCtx.font = 'bold 64px Arial';
            avatarCtx.textAlign = 'center';
            avatarCtx.textBaseline = 'middle';
            avatarCtx.fillText(initial, 64, 64);
            
            userAvatar = avatarCanvas;
            userAvatarLoaded = true;
            console.log('Fallback avatar created with initial:', initial);
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
            
            // Инициализируем renderSnake для плавной анимации
            renderSnake = snake.map(segment => ({
                x: segment.x,
                y: segment.y,
                renderX: segment.x,
                renderY: segment.y
            }));
            
            // Размещаем еду (от 1 до 3 штук)
            placeFoodItems();
            
            // Сбрасываем направление и счет
            direction = directions.RIGHT;
            nextDirection = direction;
            score = 0;
            gameOver = false;
            gameSpeed = 180;
            animationProgress = 0;
            
            // Обновляем счёт
            updateScore();
            
            // Скрываем экран окончания игры
            gameOverScreen.style.display = 'none';
            
            // Запускаем игровой цикл
            requestAnimationFrame(gameLoop);
        }
        
        // Изменение размера холста при изменении размера окна
        function resizeCanvas() {
            // Получаем размеры окна
            const width = window.innerWidth;
            const height = window.innerHeight;
            
            // Устанавливаем размеры холста
            canvas.width = width;
            canvas.height = height;
            
            // Вычисляем размер клетки исходя из размера экрана
            cellSize = Math.min(
                Math.floor(width / gridSize.width),
                Math.floor(height / gridSize.height)
            );
        }
        
        // Размещаем еду в случайных местах (от 1 до 3 штук)
        function placeFoodItems() {
            food = [];
            const foodCount = Math.floor(Math.random() * 3) + 1; // от 1 до 3 штук
            
            for (let i = 0; i < foodCount; i++) {
                placeSingleFood();
            }
        }
        
        // Размещаем один элемент еды
        function placeSingleFood() {
            let attempts = 0;
            const maxAttempts = 100;
            
            while (attempts < maxAttempts) {
                const x = Math.floor(Math.random() * gridSize.width);
                const y = Math.floor(Math.random() * gridSize.height);
                
                // Проверяем, не совпадает ли с телом змеи или существующей едой
                const isOnSnake = snake.some(segment => segment.x === x && segment.y === y);
                const isOnFood = food.some(f => f.x === x && f.y === y);
                
                if (!isOnSnake && !isOnFood) {
                    food.push({ x, y });
                    break;
                }
                attempts++;
            }
        }
        
        // Обновляем положение змейки
        function update(timestamp) {
            if (gameOver) return;
            
            // Определяем, нужно ли обновлять состояние игры
            if (timestamp - lastUpdateTime < gameSpeed) {
                // Обновляем прогресс анимации для плавного движения
                animationProgress = Math.min(1, (timestamp - lastUpdateTime) / gameSpeed);
                return;
            }
            
            lastUpdateTime = timestamp;
            animationProgress = 0;
            
            // Устанавливаем новое направление
            direction = nextDirection;
            
            // Получаем текущую голову змеи
            const head = { ...snake[0] };
            
            // Вычисляем новое положение головы
            head.x += direction.x;
            head.y += direction.y;
            
            // Проверяем столкновения с границами экрана (телепортация)
            if (head.x < 0) head.x = gridSize.width - 1;
            if (head.x >= gridSize.width) head.x = 0;
            if (head.y < 0) head.y = gridSize.height - 1;
            if (head.y >= gridSize.height) head.y = 0;
            
            // Проверяем столкновения с самой собой
            if (snake.some(segment => segment.x === head.x && segment.y === head.y)) {
                endGame();
                return;
            }
            
            // Добавляем новую голову
            snake.unshift(head);
            
            // Проверяем, съела ли змейка еду
            const eatenFoodIndex = food.findIndex(f => f.x === head.x && f.y === head.y);
            if (eatenFoodIndex !== -1) {
                // Удаляем съеденную еду
                food.splice(eatenFoodIndex, 1);
                
                score += 10;
                updateScore();
                
                // Добавляем новую еду, если еды меньше 3 штук
                if (food.length < 3) {
                    placeSingleFood();
                }
                
                // Увеличиваем скорость
                if (gameSpeed > 80) {
                    gameSpeed -= 3;
                }
            } else {
                // Если не съела, убираем последний сегмент
                snake.pop();
            }
            
            // Обновляем renderSnake для плавной анимации
            renderSnake = snake.map((segment, index) => {
                const prevSegment = renderSnake[index];
                return {
                    x: segment.x,
                    y: segment.y,
                    renderX: prevSegment ? prevSegment.renderX : segment.x,
                    renderY: prevSegment ? prevSegment.renderY : segment.y
                };
            });
            
            // Проверяем, есть ли еда на поле, если нет - добавляем
            if (food.length === 0) {
                placeFoodItems();
            }
        }
        
        // Отрисовка игры
        function draw() {
            // Очищаем холст
            ctx.fillStyle = backgroundColor;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Вычисляем смещение для центрирования игрового поля
            const offsetX = (canvas.width - cellSize * gridSize.width) / 2;
            const offsetY = (canvas.height - cellSize * gridSize.height) / 2;
            
            // Рисуем еду
            ctx.fillStyle = '#FF4136'; // красный цвет
            food.forEach(f => {
                drawRoundedRect(
                    offsetX + f.x * cellSize, 
                    offsetY + f.y * cellSize, 
                    cellSize, 
                    cellSize, 
                    cellSize / 2
                );
            });
            
            // Рисуем змейку с плавным движением и эффектом хвоста
            renderSnake.forEach((segment, index) => {
                // Интерполяция позиции для плавного движения
                const renderX = segment.renderX + (segment.x - segment.renderX) * animationProgress;
                const renderY = segment.renderY + (segment.y - segment.renderY) * animationProgress;
                
                const x = offsetX + renderX * cellSize;
                const y = offsetY + renderY * cellSize;
                
                if (index === 0) {
                    // Голова змейки - на 30% больше туловища
                    const headSize = cellSize * 1.1;
                    const headOffset = (cellSize - headSize) / 2;
                    const headX = x + headOffset;
                    const headY = y + headOffset;
                    
                    // Для головы используем аватарку пользователя
                    if (userAvatarLoaded && userAvatar) {
                        ctx.save();
                        
                        // Создаем круглую маску
                        ctx.beginPath();
                        ctx.arc(x + cellSize/2, y + cellSize/2, headSize/2 - 2, 0, 2 * Math.PI);
                        ctx.clip();
                        
                        // Рисуем аватарку
                        ctx.drawImage(userAvatar, 
                            headX + 2, 
                            headY + 2, 
                            headSize - 4, 
                            headSize - 4
                        );
                        
                        ctx.restore();
                        
                        // Добавляем контур для головы
                        ctx.strokeStyle = '#2ECC40';
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.arc(x + cellSize/2, y + cellSize/2, headSize/2 - 1, 0, 2 * Math.PI);
                        ctx.stroke();
                    } else {
                        // Fallback для головы
                        ctx.fillStyle = '#2ECC40'; // зеленый для головы
                        drawRoundedRect(
                            headX, 
                            headY, 
                            headSize, 
                            headSize, 
                            headSize / 1
                        );
                    }
                } else {
                    // Эффект хвоста - сегменты становятся меньше к концу
                    const totalSegments = renderSnake.length;
                    const tailProgress = (totalSegments - index - 1) / Math.max(1, totalSegments - 1);
                    
                    // Размер сегмента уменьшается от 80% до 40% к концу хвоста
                    const minSize = 0.4;
                    const maxSize = 0.8;
                    const sizeMultiplier = minSize + (maxSize - minSize) * tailProgress;
                    const segmentSize = cellSize * sizeMultiplier;
                    const segmentOffset = (cellSize - segmentSize) / 2;
                    
                    // Прозрачность также уменьшается к концу хвоста
                    const opacity = Math.max(0.3, 0.9 * tailProgress);
                    
                    ctx.fillStyle = `rgba(1, 255, 112, ${opacity})`; // светло-зеленый с прозрачностью
                    drawRoundedRect(
                        x + segmentOffset, 
                        y + segmentOffset, 
                        segmentSize, 
                        segmentSize, 
                        segmentSize / 1
                    );
                }
            });
            
            // Обновляем renderSnake позиции для следующего кадра
            renderSnake.forEach(segment => {
                segment.renderX = segment.renderX + (segment.x - segment.renderX) * animationProgress;
                segment.renderY = segment.renderY + (segment.y - segment.renderY) * animationProgress;
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
            
            // Вибрируем телефон, если доступно
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
