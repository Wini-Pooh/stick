# 🌐 Настройка Crontab для SWEB хостинга (boost113ic)

## ⚠️ Важно: Настройка через панель управления хостингом

На shared хостинге SWEB нужно настраивать cron задачи через **панель управления хостингом**, а не через командную строку.

## 🎯 Готовые команды для панели управления

### � Основная команда (обязательная):
```bash
cd /home/b/boost113ic/tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> logs/queue-worker.log 2>&1
```
**Расписание:** `* * * * *` (каждую минуту)

### 🔧 Дополнительная команда (рекомендуется):
```bash
cd /home/b/boost113ic/tg_sticap_ru && php8.1 artisan queue:fix-delayed --force >> logs/queue-fix.log 2>&1
```
**Расписание:** `*/5 * * * *` (каждые 5 минут)

## �️ Пошаговая инструкция для панели управления SWEB

### Шаг 1: Войти в панель управления
1. Откройте панель управления хостингом SWEB
2. Найдите раздел "**Cron**" или "**Планировщик задач**"
3. Нажмите "**Добавить новую задачу**"

### Шаг 2: Добавить основную задачу
- **Название:** `Laravel Queue Worker`
- **Команда:** 
  ```bash
  cd /home/b/boost113ic/tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> logs/queue-worker.log 2>&1
  ```
- **Расписание:** `* * * * *` или "каждую минуту"
- **Статус:** Активна

### Шаг 3: Добавить вспомогательную задачу  
- **Название:** `Fix Delayed Jobs`
- **Команда:**
  ```bash
  cd /home/b/boost113ic/tg_sticap_ru && php8.1 artisan queue:fix-delayed --force >> logs/queue-fix.log 2>&1
  ```
- **Расписание:** `*/5 * * * *` или "каждые 5 минут"
- **Статус:** Активна

## 🔍 Альтернативный способ (если панель недоступна)

Если в панели нет раздела Cron, обратитесь в **техподдержку SWEB** со следующим запросом:

```
Здравствуйте!

Прошу настроить cron задачи для моего сайта tg_sticap_ru:

1. Команда: cd /home/b/boost113ic/tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> logs/queue-worker.log 2>&1
   Расписание: каждую минуту (* * * * *)

2. Команда: cd /home/b/boost113ic/tg_sticap_ru && php8.1 artisan queue:fix-delayed --force >> logs/queue-fix.log 2>&1
   Расписание: каждые 5 минут (*/5 * * * *)

Это необходимо для работы Laravel очереди заданий.

Спасибо!
```

## 🧪 Тестирование без cron (временное решение)

Пока настраиваете cron в панели управления, можете запускать обработку очереди вручную:

### 🔄 Ручной запуск queue worker:
```bash
# Перейти в папку проекта
cd /home/b/boost113ic/tg_sticap_ru

# Запустить обработку очереди на 10 минут
php8.1 artisan queue:work --stop-when-empty --max-time=600 --sleep=3 --tries=3

# Или запустить в фоне (будет работать пока не закроете SSH)
nohup php8.1 artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> logs/queue-manual.log 2>&1 &
```

### ⚡ Быстрый тест системы:
```bash
# Создать тестовую задачу
php8.1 artisan queue:test-timing --seconds=30

# В другом терминале запустить worker
php8.1 artisan queue:work --stop-when-empty --max-time=60

# Проверить что задача выполнилась
tail logs/queue-worker.log
```

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
# Проверить статус cron задач и логов  
php8.1 artisan queue:check-cron-status

# Посмотреть логи работы очереди
tail -f logs/queue-worker.log

# Мониторинг состояния очереди
php8.1 artisan queue:monitor

# Проверить баланс пользователя
php8.1 artisan stars:manage balance ВАШ_TELEGRAM_ID
```

## ✅ После настройки cron в панели управления

### 🔍 Проверка работы (выполнить через 2-3 минуты):
```bash
# Проверить статус cron задач и логов
php8.1 artisan queue:check-cron-status

# Посмотреть логи в реальном времени
tail -f logs/queue-worker.log

# Создать тестовую задачу для проверки
php8.1 artisan queue:test-timing --seconds=30

# Тест всей системы лотереи
php8.1 artisan lottery:test --quick --user-id=ВАШ_TELEGRAM_ID
```

### 📊 Ожидаемый результат:
- ✅ Файл `logs/queue-worker.log` должен появиться и обновляться
- ✅ Тестовые задачи должны выполняться автоматически  
- ✅ Команда `queue:check-cron-status` покажет активность
- ✅ В логах будут записи о выполненных задачах

## 🔧 Если cron не работает

### 1. Проверьте команды в панели управления:
```bash
# Основная команда должна быть ТОЧНО такой:
cd /home/b/boost113ic/tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=60 --sleep=3 --tries=3 >> logs/queue-worker.log 2>&1

# Расписание: * * * * * (каждую минуту)
```

### 2. Проверьте права доступа:
```bash
# Установить права на папку логов
chmod 755 logs/
chmod 644 logs/*.log

# Проверить права на artisan
chmod 755 artisan
```

### 3. Ручной запуск для диагностики:
```bash
# Запустить команду вручную и посмотреть на ошибки
cd /home/b/boost113ic/tg_sticap_ru && php8.1 artisan queue:work --stop-when-empty --max-time=10
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
