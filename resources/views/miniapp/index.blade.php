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
        
        #speed-display {
            position: absolute;
            top: env(safe-area-inset-top, 10px);
            right: 10px;
            padding: 5px 10px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border-radius: 12px;
            font-size: 14px;
            font-weight: bold;
            z-index: 100;
        }
        
        #time-display {
            position: absolute;
            top: env(safe-area-inset-top, 40px);
            right: 10px;
            padding: 5px 10px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border-radius: 12px;
            font-size: 14px;
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
        <div id="speed-display">Скорость: 1x</div>
        <div id="time-display">Время: 0:00</div>
        <canvas id="game-canvas"></canvas>
        <div class="controls-hint">← Пока ты играешь мы работаем →</div>
        <div id="game-over">
            <h2>Игра окончена!</h2>
            <p>Ваш счёт: <span id="final-score">0</span></p>
            <p>Время игры: <span id="final-time">0:00</span></p>
            <p>Максимальная скорость: <span id="final-speed">1x</span></p>
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
        const speedDisplay = document.getElementById('speed-display');
        const timeDisplay = document.getElementById('time-display');
        const gameOverScreen = document.getElementById('game-over');
        const finalScoreDisplay = document.getElementById('final-score');
        const finalTimeDisplay = document.getElementById('final-time');
        const finalSpeedDisplay = document.getElementById('final-speed');
        const restartBtn = document.getElementById('restart-btn');
        
        let score = 0;
        let gameOver = false;
        let gameStartTime = 0;
        let currentSpeedLevel = 1;
        let lastSpeedIncreaseTime = 0;
        
        // Размер клетки игрового поля
        let cellSize = 0;
        // Количество клеток по горизонтали и вертикали
        const gridSize = { width: 20, height: 30 };
        
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
        let baseGameSpeed = 200; // базовая скорость в мс
        let gameSpeed = baseGameSpeed;
        let lastUpdateTime = 0;
        let lastFrameTime = 0;
        
        // Плавная интерполяция для анимации змейки
        let snakePositions = [];
        let animationProgress = 0;
        
        // Инициализация игры
        function initGame() {
            resizeCanvas();
            
            // Создаем змейку в центре поля
            const centerX = Math.floor(gridSize.width / 2);
            const centerY = Math.floor(gridSize.height / 2);
            snake = [
                { x: centerX, y: centerY },
                { x: centerX - 1, y: centerY },
                { x: centerX - 2, y: centerY }
            ];
            
            // Инициализируем позиции для плавной анимации
            snakePositions = snake.map(segment => ({ 
                x: segment.x, 
                y: segment.y,
                prevX: segment.x,
                prevY: segment.y
            }));
            
            // Ставим еду
            placeFood();
            
            // Сбрасываем все переменные
            direction = directions.RIGHT;
            nextDirection = direction;
            score = 0;
            gameOver = false;
            gameStartTime = Date.now();
            currentSpeedLevel = 1;
            lastSpeedIncreaseTime = gameStartTime;
            baseGameSpeed = 200;
            gameSpeed = baseGameSpeed;
            animationProgress = 0;
            
            // Обновляем отображение
            updateScore();
            updateSpeedDisplay();
            updateTimeDisplay();
            
            // Скрываем экран окончания игры
            gameOverScreen.style.display = 'none';
            
            // Запускаем игровой цикл
            lastFrameTime = performance.now();
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
                Math.floor(width / (gridSize.width + 2)), // +2 для стен по бокам
                Math.floor(height / (gridSize.height + 2)) // +2 для стен сверху и снизу
            );
        }
        
        // Размещаем еду в случайном месте
        function placeFood() {
            // Генерируем случайные координаты (с учетом стен)
            const x = Math.floor(Math.random() * (gridSize.width - 2)) + 1;
            const y = Math.floor(Math.random() * (gridSize.height - 2)) + 1;
            
            // Проверяем, не совпадает ли с телом змеи
            const isOnSnake = snake.some(segment => segment.x === x && segment.y === y);
            
            if (isOnSnake) {
                // Если еда сгенерирована на змее, пробуем еще раз
                placeFood();
            } else {
                food = { x, y };
            }
        }
        
        // Проверяем, является ли клетка стеной
        function isWall(x, y) {
            return x === 0 || y === 0 || x === gridSize.width - 1 || y === gridSize.height - 1;
        }
        
        // Обновляем положение змейки
        function update(timestamp) {
            if (gameOver) return;
            
            // Проверяем время для увеличения скорости каждые 30 секунд
            const currentTime = Date.now();
            if (currentTime - lastSpeedIncreaseTime >= 30000) { // 30 секунд
                increaseSpeed();
                lastSpeedIncreaseTime = currentTime;
            }
            
            // Обновляем отображение времени
            updateTimeDisplay();
            
            // Определяем, нужно ли обновлять состояние игры
            if (timestamp - lastUpdateTime < gameSpeed) {
                // Обновляем прогресс анимации для плавного движения
                animationProgress = Math.min(1, (timestamp - lastUpdateTime) / gameSpeed);
                return false; // Не обновляем логику, только анимацию
            }
            
            lastUpdateTime = timestamp;
            animationProgress = 0;
            
            // Сохраняем предыдущие позиции для плавной анимации
            snakePositions.forEach((pos, index) => {
                if (index < snake.length) {
                    pos.prevX = pos.x;
                    pos.prevY = pos.y;
                    pos.x = snake[index].x;
                    pos.y = snake[index].y;
                }
            });
            
            // Устанавливаем новое направление
            direction = nextDirection;
            
            // Получаем текущую голову змеи
            const head = { ...snake[0] };
            
            // Вычисляем новое положение головы
            head.x += direction.x;
            head.y += direction.y;
            
            // Проверяем столкновения со стенами
            if (isWall(head.x, head.y)) {
                endGame();
                return true;
            }
            
            // Проверяем столкновения с самой собой
            if (snake.some(segment => segment.x === head.x && segment.y === head.y)) {
                endGame();
                return true;
            }
            
            // Добавляем новую голову
            snake.unshift(head);
            
            // Проверяем, съела ли змейка еду
            if (head.x === food.x && head.y === food.y) {
                score += 10 * currentSpeedLevel; // Больше очков на высокой скорости
                updateScore();
                placeFood();
                
                // Добавляем новую позицию для анимации
                snakePositions.unshift({
                    x: head.x,
                    y: head.y,
                    prevX: head.x,
                    prevY: head.y
                });
            } else {
                // Если не съела, убираем последний сегмент
                snake.pop();
                snakePositions.pop();
            }
            
            return true;
        }
        
        // Увеличение скорости игры
        function increaseSpeed() {
            currentSpeedLevel++;
            // Уменьшаем время между обновлениями, но не менее 50ms
            gameSpeed = Math.max(50, baseGameSpeed - (currentSpeedLevel - 1) * 20);
            updateSpeedDisplay();
            
            // Визуальная обратная связь
            if ('vibrate' in navigator) {
                navigator.vibrate([50, 50, 50]);
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
            
            // Рисуем стены (белым цветом)
            ctx.fillStyle = '#FFFFFF';
            for (let x = 0; x < gridSize.width; x++) {
                for (let y = 0; y < gridSize.height; y++) {
                    if (isWall(x, y)) {
                        ctx.fillRect(
                            offsetX + x * cellSize, 
                            offsetY + y * cellSize, 
                            cellSize, 
                            cellSize
                        );
                    }
                }
            }
            
            // Рисуем еду с пульсацией
            const pulseScale = 0.9 + 0.1 * Math.sin(Date.now() * 0.005);
            const foodSize = cellSize * pulseScale;
            const foodOffset = (cellSize - foodSize) / 2;
            ctx.fillStyle = '#FF4136'; // красный цвет
            drawRoundedRect(
                offsetX + food.x * cellSize + foodOffset, 
                offsetY + food.y * cellSize + foodOffset, 
                foodSize, 
                foodSize, 
                foodSize / 3
            );
            
            // Рисуем змейку с плавной анимацией
            snakePositions.forEach((pos, index) => {
                if (index >= snake.length) return;
                
                // Вычисляем интерполированную позицию
                const interpX = pos.prevX + (pos.x - pos.prevX) * animationProgress;
                const interpY = pos.prevY + (pos.y - pos.prevY) * animationProgress;
                
                // Для головы используем другой цвет и добавляем глаза
                if (index === 0) {
                    ctx.fillStyle = '#2ECC40'; // зеленый для головы
                    drawRoundedRect(
                        offsetX + interpX * cellSize, 
                        offsetY + interpY * cellSize, 
                        cellSize, 
                        cellSize, 
                        cellSize / 3
                    );
                    
                    // Рисуем глаза
                    ctx.fillStyle = '#FFFFFF';
                    const eyeSize = cellSize * 0.15;
                    const eyeOffsetX = cellSize * 0.25;
                    const eyeOffsetY = cellSize * 0.25;
                    
                    // Левый глаз
                    ctx.beginPath();
                    ctx.arc(
                        offsetX + interpX * cellSize + eyeOffsetX,
                        offsetY + interpY * cellSize + eyeOffsetY,
                        eyeSize, 0, 2 * Math.PI
                    );
                    ctx.fill();
                    
                    // Правый глаз
                    ctx.beginPath();
                    ctx.arc(
                        offsetX + interpX * cellSize + cellSize - eyeOffsetX,
                        offsetY + interpY * cellSize + eyeOffsetY,
                        eyeSize, 0, 2 * Math.PI
                    );
                    ctx.fill();
                } else {
                    // Тело змейки с градиентом
                    const alpha = 1 - (index * 0.05); // Постепенное затухание
                    ctx.fillStyle = `rgba(1, 255, 112, ${Math.max(0.3, alpha)})`;
                    drawRoundedRect(
                        offsetX + interpX * cellSize, 
                        offsetY + interpY * cellSize, 
                        cellSize, 
                        cellSize, 
                        cellSize / 3
                    );
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
        
        // Обновляем отображение счета
        function updateScore() {
            scoreDisplay.textContent = `Счёт: ${score}`;
        }
        
        // Обновляем отображение скорости
        function updateSpeedDisplay() {
            speedDisplay.textContent = `Скорость: ${currentSpeedLevel}x`;
        }
        
        // Обновляем отображение времени
        function updateTimeDisplay() {
            const elapsed = Math.floor((Date.now() - gameStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            timeDisplay.textContent = `Время: ${minutes}:${seconds.toString().padStart(2, '0')}`;
        }
        
        // Форматирование времени для финального экрана
        function formatTime(startTime) {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            return `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }
        
        // Обработка окончания игры
        function endGame() {
            gameOver = true;
            finalScoreDisplay.textContent = score;
            finalTimeDisplay.textContent = formatTime(gameStartTime);
            finalSpeedDisplay.textContent = `${currentSpeedLevel}x`;
            gameOverScreen.style.display = 'flex';
            
            // Вибрируем телефон, если доступно
            if ('vibrate' in navigator) {
                navigator.vibrate(200);
            }
        }
        
        // Основной игровой цикл с точным контролем FPS
        function gameLoop(timestamp) {
            if (gameOver) return;
            
            // Ограничиваем FPS до 60
            const deltaTime = timestamp - lastFrameTime;
            if (deltaTime < 16.67) { // ~60 FPS
                requestAnimationFrame(gameLoop);
                return;
            }
            lastFrameTime = timestamp;
            
            const logicUpdated = update(timestamp);
            draw();
            
            requestAnimationFrame(gameLoop);
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
        
        // Запуск игры при загрузке страницы
        window.addEventListener('load', () => {
            initGame();
        });
        
        // Запуск игры, если страница уже загружена
        if (document.readyState === 'complete') {
            initGame();
        }
    </script>
</body>
</html>
