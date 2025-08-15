<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Змейка | Telegram Mini App</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        /* Высокоприоритетные стили для перебивания Telegram стилей */
        html, html body {
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
            touch-action: none !important;
            -webkit-touch-callout: none !important;
            -webkit-user-select: none !important;
            -khtml-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            user-select: none !important;
        }
        
        html body.miniapp-game {
            background-color: var(--tg-theme-bg-color, #000000) !important;
            color: var(--tg-theme-text-color, #ffffff) !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
            overscroll-behavior: contain !important;
            overflow: hidden !important;
            position: fixed !important;
            width: 100% !important;
            height: 100% !important;
            min-height: 100vh !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
        }
        
        body.miniapp-game #game-container {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            z-index: 1000 !important;
        }
        
        body.miniapp-game #game-canvas {
            width: 100% !important;
            height: 100% !important;
            display: block !important;
            border: none !important;
            outline: none !important;
            background: transparent !important;
        }
        
        body.miniapp-game #score-display {
            position: absolute !important;
            top: env(safe-area-inset-top, 10px) !important;
            left: 10px !important;
            padding: 5px 10px !important;
            background-color: rgba(0, 0, 0, 0.7) !important;
            color: white !important;
            border-radius: 12px !important;
            font-size: 18px !important;
            font-weight: bold !important;
            z-index: 1100 !important;
            border: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
        }
        
        body.miniapp-game #game-over {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background-color: rgba(0, 0, 0, 0.9) !important;
            display: none !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            z-index: 1200 !important;
            color: white !important;
        }
        
        body.miniapp-game #game-over h2 {
            font-size: 32px !important;
            margin-bottom: 20px !important;
            color: white !important;
            font-weight: bold !important;
            text-align: center !important;
        }
        
        body.miniapp-game #game-over p {
            font-size: 24px !important;
            margin-bottom: 30px !important;
            color: white !important;
            text-align: center !important;
        }
        
        body.miniapp-game .btn,
        body.miniapp-game #restart-btn {
            padding: 12px 24px !important;
            background-color: var(--tg-theme-button-color, #2AABEE) !important;
            color: var(--tg-theme-button-text-color, #ffffff) !important;
            border: none !important;
            border-radius: 12px !important;
            font-size: 18px !important;
            font-weight: bold !important;
            cursor: pointer !important;
            margin-top: 10px !important;
            outline: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
            text-decoration: none !important;
            display: inline-block !important;
            min-width: 120px !important;
            text-align: center !important;
        }
        
        body.miniapp-game .btn:hover,
        body.miniapp-game #restart-btn:hover {
            opacity: 0.8 !important;
            transform: translateY(-1px) !important;
            transition: all 0.2s ease !important;
        }
        
        body.miniapp-game .controls-hint {
            position: absolute !important;
            bottom: env(safe-area-inset-bottom, 20px) !important;
            left: 0 !important;
            width: 100% !important;
            text-align: center !important;
            color: rgba(255, 255, 255, 0.6) !important;
            font-size: 14px !important;
            pointer-events: none !important;
            opacity: 0.8 !important;
            z-index: 1100 !important;
            padding: 10px !important;
        }
        
        /* Дополнительная защита от стилей Telegram */
        body.miniapp-game * {
            -webkit-tap-highlight-color: transparent !important;
            -webkit-focus-ring-color: transparent !important;
        }
    </style>
</head>
<body class="miniapp-game">
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
        
        // Принудительно применяем стили для перебивания Telegram стилей
        function enforceGameStyles() {
            const body = document.body;
            const gameContainer = document.getElementById('game-container');
            
            // Принудительно устанавливаем стили body
            body.style.setProperty('margin', '0', 'important');
            body.style.setProperty('padding', '0', 'important');
            body.style.setProperty('overflow', 'hidden', 'important');
            body.style.setProperty('position', 'fixed', 'important');
            body.style.setProperty('width', '100%', 'important');
            body.style.setProperty('height', '100%', 'important');
            body.style.setProperty('top', '0', 'important');
            body.style.setProperty('left', '0', 'important');
            body.style.setProperty('right', '0', 'important');
            body.style.setProperty('bottom', '0', 'important');
            
            // Принудительно устанавливаем стили игрового контейнера
            if (gameContainer) {
                gameContainer.style.setProperty('position', 'absolute', 'important');
                gameContainer.style.setProperty('top', '0', 'important');
                gameContainer.style.setProperty('left', '0', 'important');
                gameContainer.style.setProperty('width', '100%', 'important');
                gameContainer.style.setProperty('height', '100%', 'important');
                gameContainer.style.setProperty('z-index', '1000', 'important');
            }
        }
        
        // Применяем стили при загрузке и через интервал для надежности
        enforceGameStyles();
        setInterval(enforceGameStyles, 100);
        
        // Получаем цвета из темы Telegram
        const backgroundColor = tg.themeParams.bg_color || '#000000';
        const textColor = tg.themeParams.text_color || '#ffffff';
        const buttonColor = tg.themeParams.button_color || '#2AABEE';
        const buttonTextColor = tg.themeParams.button_text_color || '#ffffff';
        
        // Применяем цвета принудительно
        document.body.style.setProperty('background-color', backgroundColor, 'important');
        document.body.style.setProperty('color', textColor, 'important');
        
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
        let gameSpeed = 150; // начальная скорость змейки в мс
        let lastUpdateTime = 0;
        
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
            
            // Определяем, нужно ли обновлять состояние игры
            if (timestamp - lastUpdateTime < gameSpeed) return;
            lastUpdateTime = timestamp;
            
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
                return;
            }
            
            // Проверяем столкновения с самой собой
            if (snake.some(segment => segment.x === head.x && segment.y === head.y)) {
                endGame();
                return;
            }
            
            // Добавляем новую голову
            snake.unshift(head);
            
            // Проверяем, съела ли змейка еду
            if (head.x === food.x && head.y === food.y) {
                score += 10;
                updateScore();
                placeFood();
                
                // Увеличиваем скорость
                if (gameSpeed > 50) {
                    gameSpeed -= 2;
                }
            } else {
                // Если не съела, убираем последний сегмент
                snake.pop();
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
            
            // Рисуем еду
            ctx.fillStyle = '#FF4136'; // красный цвет
            drawRoundedRect(
                offsetX + food.x * cellSize, 
                offsetY + food.y * cellSize, 
                cellSize, 
                cellSize, 
                cellSize / 3
            );
            
            // Рисуем змейку
            snake.forEach((segment, index) => {
                // Для головы используем другой цвет
                if (index === 0) {
                    ctx.fillStyle = '#2ECC40'; // зеленый для головы
                } else {
                    ctx.fillStyle = '#01FF70'; // светло-зеленый для тела
                }
                
                drawRoundedRect(
                    offsetX + segment.x * cellSize, 
                    offsetY + segment.y * cellSize, 
                    cellSize, 
                    cellSize, 
                    cellSize / 3
                );
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
        
        // Основной игровой цикл
        function gameLoop(timestamp) {
            update(timestamp);
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
        
        // Запускаем игру при загрузке страницы
        window.addEventListener('load', () => {
            initGame();
        });
    </script>
</body>
</html>
