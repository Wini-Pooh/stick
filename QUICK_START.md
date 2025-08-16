# ⚡ Быстрый запуск системы лотереи

## 🚀 На хостинге (5 команд)

```bash
# 1. Создание таблицы для очередей
php8.1 artisan queue:table

# 2. Запуск миграций
php8.1 artisan migrate

# 3. Настройка
php8.1 artisan bot:check-stars-setup --fix

# 4. Запуск worker'а (в фоне или отдельном терминале)
php8.1 artisan queue:work --sleep=3 --tries=3 --max-time=3600

# 5. Тестирование
php8.1 artisan lottery:test --quick --user-id=ВАШ_TELEGRAM_ID
```

## 🧪 Для локального тестирования

```bash
# 1. Полный тест системы
php8.1 artisan lottery:test --quick

# 2. Мониторинг очереди
php8.1 artisan queue:monitor --watch

# 3. Проверка настроек
php artisan bot:check-stars-setup

# 4. Тест работы отложенных задач (30 сек)
php8.1 artisan queue:test-timing --seconds=30

# 5. Исправление отложенных задач
php8.1 artisan queue:fix-delayed
```

## 📋 Что должно работать после запуска:

1. ✅ **Оплата** - пользователь платит 1 ⭐ за билет
2. ⏰ **Ожидание** - система ждет 1 минуту
3. 🎲 **Розыгрыш** - определяется выигрыш/проигрыш
4. 📱 **Уведомление** - пользователю приходит результат
5. 💰 **Начисление** - если выиграл, звезды зачисляются

## ❗ Если что-то не работает:

```bash
# Если ошибка "Table jobs doesn't exist"
php8.1 artisan queue:table
php8.1 artisan migrate

# Если ошибка "is_winner cannot be null"  
php8.1 artisan migrate

# Если ошибка "chat not found" - используйте реальный ID
php8.1 artisan lottery:test --quick --user-id=ВАШ_TELEGRAM_ID

# ИСПРАВЛЕНИЕ ЧАСОВОГО ПОЯСА
php8.1 artisan timezone:fix --clear-queue

# Исправление проблем с отложенными задачами
php8.1 artisan queue:fix-delayed --force

# Диагностика после исправления
php8.1 artisan lottery:test --quick

# Просмотр логов
tail -f storage/logs/laravel.log

# Перезапуск worker'а (ОБЯЗАТЕЛЬНО после изменений)
php8.1 artisan queue:restart
php8.1 artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## ✅ Результат успешного теста:
- 🎯 13-14 тестов из 14 должны пройти
- ❌ Если "chat not found" - нормально (тестовый ID)
- 🚀 Система готова к работе!

## 🚨 ЭКСТРЕННОЕ ИСПРАВЛЕНИЕ - Задачи не выполняются автоматически

Если задачи висят в очереди и не выполняются:

```bash
# 1. Остановить текущий worker (Ctrl+C)

# 2. Исправить часовой пояс и очистить старые задачи
php8.1 artisan timezone:fix --clear-queue

# 3. Принудительно выполнить просроченные задачи
php8.1 artisan queue:fix-delayed --force

# 4. Перезапустить worker с правильными параметрами
php8.1 artisan queue:restart
php8.1 artisan queue:work --sleep=3 --tries=3 --max-time=3600

# 5. Проверить что всё работает
php8.1 artisan lottery:test --user-id=ВАШ_TELEGRAM_ID
```

**Важно**: `--sleep=3` заставляет worker проверять новые задачи каждые 3 секунды!

## 📞 Поддержка

При проблемах проверьте:
1. Запущен ли worker очереди
2. QUEUE_CONNECTION=database в .env
3. Настроен ли webhook для Stars платежей
