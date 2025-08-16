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
php8.1 artisan queue:work --timeout=300 --sleep=3 --tries=3

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

# Диагностика после исправления
php8.1 artisan lottery:test --quick

# Просмотр логов
tail -f storage/logs/laravel.log

# Перезапуск worker'а
php8.1 artisan queue:monitor --clear
php8.1 artisan queue:work --timeout=300
```

## ✅ Результат успешного теста:
- 🎯 13-14 тестов из 14 должны пройти
- ❌ Если "chat not found" - нормально (тестовый ID)
- 🚀 Система готова к работе!

## 📞 Поддержка

При проблемах проверьте:
1. Запущен ли worker очереди
2. QUEUE_CONNECTION=database в .env
3. Настроен ли webhook для Stars платежей
