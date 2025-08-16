# ⚡ Быстрый запуск системы лотереи

## 🚀 На хостинге (3 команды)

```bash
# 1. Настройка
php8.1 artisan bot:check-stars-setup --fix

# 2. Запуск worker'а (в фоне или отдельном терминале)
php8.1 artisan queue:work --timeout=300 --sleep=3 --tries=3

# 3. Тестирование
php8.1 artisan lottery:test --quick --user-id=ВАШ_TELEGRAM_ID
```

## 🧪 Для локального тестирования

```bash
# 1. Полный тест системы
php artisan lottery:test --quick

# 2. Мониторинг очереди
php artisan queue:monitor --watch

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
# Диагностика
php8.1 artisan lottery:test --quick

# Просмотр логов
tail -f storage/logs/laravel.log

# Перезапуск
php8.1 artisan queue:monitor --clear
php8.1 artisan queue:work --timeout=300
```

## 📞 Поддержка

При проблемах проверьте:
1. Запущен ли worker очереди
2. QUEUE_CONNECTION=database в .env
3. Настроен ли webhook для Stars платежей
