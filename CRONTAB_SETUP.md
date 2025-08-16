# 🕐 Настройка Crontab для обработки очереди jobs

## ⚡ Быстрая настройка

### 1. Открыть редактор crontab:
```bash
crontab -e
```

### 2. Добавить строку для запуска queue worker каждую минуту:
```bash
# Обработка очереди Laravel каждую минуту
* * * * * cd /path/to/your/project && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> /dev/null 2>&1

# Альтернативно: с логированием
* * * * * cd tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> /var/log/laravel-queue.log 2>&1
```

### 3. Для хостинга (замените путь на свой):
```bash
# Для хостинга OSPanel или обычного хостинга
* * * * * cd /home/your-username/domains/tgstick && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> /dev/null 2>&1

# Или через полный путь к PHP
* * * * * cd /home/your-username/domains/tgstick && /usr/bin/php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> /dev/null 2>&1
```

## 🔧 Параметры команды

- `* * * * *` - каждую минуту
- `--stop-when-empty` - остановиться когда очередь пуста
- `--max-time=60` - работать максимум 60 секунд
- `--sleep=3` - ждать 3 секунды между проверками
- `--tries=3` - 3 попытки выполнения при ошибке
- `>> /dev/null 2>&1` - не выводить логи (тихий режим)

## 📋 Альтернативные варианты

### Вариант 1: С подробным логированием
```bash
* * * * * cd /path/to/your/project && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 --verbose >> /var/log/laravel-queue.log 2>&1
```

### Вариант 2: Только для определённой очереди
```bash
* * * * * cd /path/to/your/project && php8.1 artisan queue:work --queue=default --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> /dev/null 2>&1
```

### Вариант 3: Запуск через supervisor (рекомендуется для продакшена)
```bash
# В crontab только проверка что supervisor работает
* * * * * supervisorctl status laravel-worker | grep RUNNING > /dev/null || supervisorctl start laravel-worker
```

## 🚀 Настройка для разных окружений

### OSPanel (Windows):
```bash
# В crontab Linux подсистемы или через планировщик Windows
* * * * * cd /c/ospanel/domains/tgstick && /c/ospanel/modules/php/PHP_8.1/php.exe artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3
```

### Обычный Linux хостинг:
```bash
* * * * * cd /home/username/public_html/tgstick && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> /dev/null 2>&1
```

### VPS/Dedicated сервер:
```bash
* * * * * cd /var/www/html/tgstick && php artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> /dev/null 2>&1
```

### 🌐 Хостинг SWEB (boost113ic):
```bash
# Основная команда для обработки очереди каждую минуту
* * * * * cd tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> logs/queue-worker.log 2>&1

# Исправление зависших задач каждые 5 минут
*/5 * * * * cd tg_sticap_ru && php8.1 artisan queue:fix-delayed --force >> logs/queue-fix.log 2>&1

# Мониторинг состояния каждые 30 минут
*/30 * * * * cd tg_sticap_ru && php8.1 artisan queue:monitor >> logs/queue-monitor.log 2>&1

# Очистка старых логов ежедневно в 2:00
0 2 * * * find tg_sticap_ru/storage/logs/*.log -type f -mtime +7 -delete 2>/dev/null
```

### 📋 Готовые команды для копирования в crontab (SWEB):
```bash
# Откройте crontab редактор:
crontab -e

# Скопируйте и вставьте эти строки:
# Laravel Queue Worker для tg_sticap_ru
* * * * * cd tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> logs/queue-worker.log 2>&1
*/5 * * * * cd tg_sticap_ru && php8.1 artisan queue:fix-delayed --force >> logs/queue-fix.log 2>&1
```

## ✅ Проверка работы

### 1. Проверить что crontab создан:
```bash
crontab -l
```

### 2. Проверить логи cron:
```bash
# CentOS/RHEL
tail -f /var/log/cron

# Ubuntu/Debian  
tail -f /var/log/syslog | grep CRON
```

### 3. Проверить работу очереди:
```bash
# Мониторинг очереди
php artisan queue:monitor --watch

# Создать тестовую задачу
php artisan queue:test-timing --seconds=30

# Проверить статус
php artisan queue:monitor
```

### 4. Специально для SWEB хостинга:
```bash
# Зайти в папку проекта
cd tg_sticap_ru

# Проверить права доступа
ls -la artisan
chmod 755 artisan

# Проверить PHP версию
php8.1 --version

# Создать папку для логов если её нет
mkdir -p logs

# Тестировать команду queue worker
php8.1 artisan queue:work --stop-when-empty --max-time=10

# Посмотреть логи crontab
tail -f logs/queue-worker.log

# Проверить текущий crontab
crontab -l
```

## 🔍 Отладка проблем

### Если задачи не выполняются:

1. **Проверить права доступа:**
```bash
ls -la /path/to/your/project/
chmod 755 /path/to/your/project/artisan
```

2. **Проверить PHP путь:**
```bash
which php8.1
# Использовать полный путь в crontab
```

3. **Проверить переменные окружения:**
```bash
# Добавить в начало crontab
PATH=/usr/local/bin:/usr/bin:/bin
```

4. **Тестировать команду вручную:**
```bash
cd /path/to/your/project && php8.1 artisan queue:work --stop-when-empty --max-time=10
```

### 🌐 Для SWEB хостинга (boost113ic):
```bash
# Перейти в папку проекта
cd tg_sticap_ru

# Тестировать команду queue worker
php8.1 artisan queue:work --stop-when-empty --max-time=10

# Создать тестовую задачу лотереи
php8.1 artisan lottery:test --quick --user-id=ВАШ_TELEGRAM_ID

# Проверить тест выплаты выигрышей
php8.1 artisan lottery:test-winning-payout --user-id=ВАШ_TELEGRAM_ID --amount=5

# Проверить баланс звезд пользователя
php8.1 artisan stars:manage balance ВАШ_TELEGRAM_ID
```

## 🎯 Специально для лотереи

### Команда оптимизированная для ProcessLotteryResult:
```bash
# Каждую минуту обрабатывать отложенные задачи лотереи
* * * * * cd /path/to/your/project && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=1 --tries=5 --timeout=300 >> /var/log/lottery-queue.log 2>&1
```

### Дополнительная команда для исправления зависших задач:
```bash
# Каждые 5 минут исправлять зависшие задачи
*/5 * * * * cd /path/to/your/project && php8.1 artisan queue:fix-delayed --force >> /var/log/lottery-fix.log 2>&1
```

## 📝 Полный пример crontab для лотереи:

```bash
# Laravel Queue Worker - каждую минуту
* * * * * cd /home/username/domains/tgstick && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> /var/log/laravel-queue.log 2>&1

# Исправление зависших задач - каждые 5 минут  
*/5 * * * * cd /home/username/domains/tgstick && php8.1 artisan queue:fix-delayed --force >> /var/log/lottery-fix.log 2>&1

# Очистка старых логов - каждый день в 2:00
0 2 * * * find /var/log/laravel-queue.log -mtime +7 -delete

# Мониторинг состояния - каждые 30 минут
*/30 * * * * cd /home/username/domains/tgstick && php8.1 artisan queue:monitor >> /var/log/queue-monitor.log 2>&1
```

## ⚠️ Важные заметки:

1. **Замените `/path/to/your/project`** на реальный путь к проекту
2. **Проверьте версию PHP** (php8.1, php8.0, php)
3. **Настройте логирование** по необходимости
4. **Тестируйте** команды перед добавлением в crontab
5. **Мониторьте** выполнение первые несколько дней
