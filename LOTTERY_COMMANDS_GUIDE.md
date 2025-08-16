# 🎰 Система Лотереи - Руководство по командам

## 📋 Описание команд

### 🧪 Тестирование системы

#### `php artisan lottery:test`
**Назначение**: Полное тестирование системы лотереи от оплаты до выигрыша/проигрыша

**Параметры**:
- `--full` - Полный тест с эмуляцией платежа
- `--quick` - Быстрый тест без задержки (результат сразу)
- `--user-id=123456` - ID пользователя для теста (по умолчанию 999999999)

**Что тестируется**:
1. ✅ Подключение к Telegram Bot API
2. ✅ Подключение к базе данных
3. ✅ Настройка очередей
4. ✅ Структура таблиц БД
5. ✅ Создание тестового пользователя
6. ✅ Создание тестового билета
7. ✅ Эмуляция успешной оплаты
8. ✅ Обработка результата лотереи (через Job)
9. ✅ Система уведомлений Telegram
10. ✅ Начисление выигрыша

**Примеры использования**:
```bash
# Обычный тест (с задержкой 1 минута)
php artisan lottery:test

# Быстрый тест (без задержки)
php artisan lottery:test --quick

# Тест с конкретным пользователем
php artisan lottery:test --user-id=1107317588 --quick
```

---

### 📊 Мониторинг очереди

#### `php artisan queue:monitor`
**Назначение**: Мониторинг состояния очереди и активных задач

**Параметры**:
- `--watch` - Постоянное наблюдение (обновление каждые 5 секунд)
- `--clear` - Очистить заблокированные и старые задачи

**Что показывает**:
- 📋 Количество задач в очереди
- ❌ Количество неудачных задач
- 🔍 Детали последних 10 задач
- 📈 Статистика по типам задач
- ⚙️ Инструкции по запуску worker'а
- 📝 Последние записи в логах

**Примеры использования**:
```bash
# Показать текущее состояние
php artisan queue:monitor

# Постоянное наблюдение
php artisan queue:monitor --watch

# Очистка заблокированных задач
php artisan queue:monitor --clear
```

---

### ⭐ Управление Stars

#### `php artisan stars:manage`
**Назначение**: Управление Telegram Stars: подарить, вернуть или проверить баланс

**Параметры**:
- `action` - Действие: `gift`, `refund`, `balance`
- `user_id` - Telegram ID пользователя
- `amount` - Количество звезд (для gift и refund)
- `--reason="причина"` - Причина операции

**Примеры использования**:
```bash
# Подарить 100 звезд
php artisan stars:manage gift 1107317588 100 --reason="Компенсация за ошибку"

# Вернуть 50 звезд
php artisan stars:manage refund 1107317588 50 --reason="Возврат по запросу"

# Проверить баланс
php artisan stars:manage balance 1107317588
```

---

### 🔧 Настройка системы

#### `php artisan bot:check-stars-setup`
**Назначение**: Проверка полной настройки Telegram Stars платежей

**Параметры**:
- `--fix` - Автоматически исправить найденные проблемы

**Что проверяется**:
- 🤖 Токен бота и подключение к API
- 🔗 Настройка webhook
- 📡 URL webhook и доступность
- 🛣️ Настройка роутов
- 🎛️ Методы контроллера
- ⚙️ Переменные окружения
- 📄 Пример создания инвойса

#### `php artisan bot:setup-hosting`
**Назначение**: Настройка окружения для хостинга

**Параметры**:
- `--force` - Принудительная перезапись .env

#### `php artisan bot:test-stars-payment`
**Назначение**: Тестирование отправки Stars платежа

**Параметры**:
- `user_id` - ID пользователя для теста (необязательно)

---

## 🚀 Запуск системы на хостинге

### 1. Подготовка окружения
```bash
# Настройка переменных среды
php8.1 artisan bot:setup-hosting

# Проверка настроек
php8.1 artisan bot:check-stars-setup

# Автоисправление проблем (если нужно)
php8.1 artisan bot:check-stars-setup --fix
```

### 2. Запуск worker'а очереди
```bash
# Основной worker для обработки задач
php8.1 artisan queue:work --timeout=300 --sleep=3 --tries=3 --daemon

# Альтернативно - без daemon режима
php8.1 artisan queue:work --timeout=300 --sleep=3 --tries=3
```

### 3. Мониторинг
```bash
# Проверка состояния очереди
php8.1 artisan queue:monitor

# Постоянное наблюдение
php8.1 artisan queue:monitor --watch

# Просмотр логов
tail -f storage/logs/laravel.log
```

### 4. Тестирование
```bash
# Полный тест системы
php8.1 artisan lottery:test --quick

# Тест конкретного пользователя
php8.1 artisan lottery:test --user-id=ВАШ_ID --quick

# Тест платежей
php8.1 artisan bot:test-stars-payment ВАШ_ID
```

---

## 🔍 Диагностика проблем

### Проблема: Задачи не выполняются
**Проверить**:
1. `php8.1 artisan queue:monitor` - есть ли задачи в очереди?
2. Запущен ли worker: `php8.1 artisan queue:work`
3. QUEUE_CONNECTION=database в .env?

### Проблема: Уведомления не приходят
**Проверить**:
1. `php8.1 artisan bot:check-stars-setup` - настроен ли webhook?
2. Доступен ли бот по токену?
3. Правильный ли chat_id?

### Проблема: Выигрыш не начисляется
**Проверить**:
1. Создается ли запись в star_transactions?
2. Обновляется ли stars_balance у пользователя?
3. Выполняется ли Job ProcessLotteryResult?

### Проблема: Ошибки в логах
**Команды для диагностики**:
```bash
# Просмотр последних ошибок
tail -100 storage/logs/laravel.log | grep ERROR

# Поиск ошибок лотереи
grep -i "lottery\|stars\|ProcessLotteryResult" storage/logs/laravel.log

# Очистка старых логов
> storage/logs/laravel.log
```

---

## 📝 Логи и отладка

### Важные события в логах:
- `🌟 Pre-checkout query received` - Получен запрос предварительной проверки
- `✅ Pre-checkout query approved` - Предварительная проверка одобрена
- `🌟 Successful payment received` - Получен успешный платеж
- `✅ Lotto ticket payment confirmed` - Платеж за билет подтвержден
- `🎲 Lottery draw result` - Результат розыгрыша определен
- `💰 Stars credited to user` - Звезды начислены пользователю

### Структура логов:
```
[INFO] 🎲 Lottery draw result {
    "ticket_id": 123,
    "ticket_number": "LT20250816ABC123",
    "random_value": 0.3456,
    "win_chance": 0.5,
    "is_winner": false
}
```

---

## 🔄 Обслуживание системы

### Ежедневные задачи:
1. Проверка состояния очереди: `php8.1 artisan queue:monitor`
2. Очистка старых задач: `php8.1 artisan queue:monitor --clear`
3. Проверка логов на ошибки

### Еженедельные задачи:
1. Очистка старых логов
2. Проверка настроек webhook: `php8.1 artisan bot:check-stars-setup`
3. Тестирование системы: `php8.1 artisan lottery:test --quick`

### При проблемах:
1. Остановить worker: `Ctrl+C`
2. Очистить очередь: `php8.1 artisan queue:monitor --clear`
3. Перезапустить worker: `php8.1 artisan queue:work --timeout=300`
4. Проверить настройки: `php8.1 artisan bot:check-stars-setup --fix`
