# 🌐 Настройка Crontab для SWEB хостинга (boost113ic)

## ⚡ Готовые команды для копирования

### 🕐 Шаг 1: Открыть crontab
```bash
crontab -e
```

### 📋 Шаг 2: Скопировать и вставить эти строки:

```bash
# Laravel Queue Worker для tg_sticap_ru - каждую минуту
* * * * * cd tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> logs/queue-worker.log 2>&1

# Исправление зависших задач - каждые 5 минут
*/5 * * * * cd tg_sticap_ru && php8.1 artisan queue:fix-delayed --force >> logs/queue-fix.log 2>&1

# Мониторинг состояния - каждые 30 минут (опционально)
*/30 * * * * cd tg_sticap_ru && php8.1 artisan queue:monitor >> logs/queue-monitor.log 2>&1

# Очистка старых логов - ежедневно в 2:00 (опционально)
0 2 * * * find tg_sticap_ru/storage/logs/*.log -type f -mtime +7 -delete 2>/dev/null
```

### 💾 Шаг 3: Сохранить и выйти
- Нажмите `Ctrl+X`, затем `Y`, затем `Enter`

## 🧪 Тестирование настройки

### 1. Подготовка (выполнить один раз):
```bash
# Перейти в папку проекта
cd tg_sticap_ru

# Создать папку для логов
mkdir -p logs

# Проверить права доступа на artisan
chmod 755 artisan

# Проверить PHP версию
php8.1 --version

# Создать таблицу для очереди (если не создана)
php8.1 artisan queue:table

# Запустить миграции
php8.1 artisan migrate
```

### 2. Тестирование системы:
```bash
# Быстрый тест лотереи
php8.1 artisan lottery:test --quick --user-id=ВАШ_TELEGRAM_ID

# Тест выплаты выигрышей
php8.1 artisan lottery:test-winning-payout --user-id=ВАШ_TELEGRAM_ID --amount=10

# Проверить настройки бота
php8.1 artisan bot:check-stars-setup --fix

# Тест отложенных задач (30 секунд)
php8.1 artisan queue:test-timing --seconds=30
```

### 3. Проверка работы crontab:
```bash
# Проверить что crontab установлен
crontab -l

# Посмотреть логи работы очереди
tail -f logs/queue-worker.log

# Мониторинг состояния очереди
php8.1 artisan queue:monitor

# Проверить баланс пользователя
php8.1 artisan stars:manage balance ВАШ_TELEGRAM_ID
```

## 📊 Что будет происходить

### ✅ Автоматически каждую минуту:
1. **Проверка очереди** - есть ли задачи к выполнению
2. **Обработка ProcessLotteryResult** - выполнение отложенных розыгрышей
3. **Выплата выигрышей** - зачисление звезд победителям
4. **Уведомления пользователям** - отправка результатов в Telegram
5. **Логирование** - запись всех операций в logs/queue-worker.log

### 🔧 Исправление проблем каждые 5 минут:
1. **Зависшие задачи** - перезапуск заблокированных jobs
2. **Просроченные розыгрыши** - принудительное выполнение
3. **Очистка старых задач** - удаление неактуальных записей

## 🔍 Мониторинг и отладка

### Команды для диагностики:
```bash
# Посмотреть активные задачи в очереди
php8.1 artisan queue:monitor

# Посмотреть последние 50 строк лога
tail -50 logs/queue-worker.log

# Посмотреть логи в реальном времени
tail -f logs/queue-worker.log

# Проверить есть ли ошибки
grep "ERROR\|FAILED" logs/queue-worker.log

# Посмотреть статистику выполнения
grep "processed\|completed" logs/queue-worker.log | tail -10
```

### Если что-то не работает:
```bash
# 1. Проверить что crontab работает
crontab -l

# 2. Ручной запуск для тестирования
cd tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=10

# 3. Проверить права доступа
ls -la artisan
chmod 755 artisan

# 4. Проверить базу данных
php8.1 artisan migrate:status

# 5. Создать тестовую задачу
php8.1 artisan queue:test-timing --seconds=10
```

## 🎯 Ожидаемый результат

После настройки crontab:
- ✅ Лотерея работает автоматически 24/7
- ✅ Розыгрыши выполняются через 1 минуту после покупки
- ✅ Выигрыши моментально зачисляются пользователям
- ✅ Все операции логируются для контроля
- ✅ Система самостоятельно исправляет проблемы

## 📞 Поддержка

Если возникли проблемы:
1. Проверьте логи: `tail -f logs/queue-worker.log`
2. Убедитесь что crontab установлен: `crontab -l`
3. Протестируйте команды вручную
4. Проверьте права доступа к файлам проекта
