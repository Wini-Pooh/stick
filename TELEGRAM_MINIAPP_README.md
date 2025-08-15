# Telegram Mini App

Этот проект представляет собой Telegram Mini App на базе Laravel, который позволяет пользователям просматривать свой профиль и отладочную информацию прямо в Telegram.

## 🚀 Функциональность

- ✅ Авторизация через Telegram (проверка подписи initData)
- ✅ Отображение профиля пользователя Telegram
- ✅ Подробная отладочная информация
- ✅ Адаптивный дизайн для мобильных устройств
- ✅ Поддержка тем Telegram
- ✅ Telegram Bot с кнопкой для открытия Mini App

## 📱 Установка и настройка

### 1. Настройка бота

1. Создайте бота через [@BotFather](https://t.me/BotFather)
2. Получите токен бота и добавьте его в `.env`:
   ```
   TELEGRAM_BOT_TOKEN=ваш_токен_бота
   TELEGRAM_BOT_USERNAME=имя_вашего_бота
   ```

### 2. Настройка Mini App в BotFather

1. Отправьте команду `/newapp` в [@BotFather]
2. Выберите вашего бота
3. Введите название приложения
4. Введите описание
5. Загрузите иконку (512x512 PNG)
6. Введите URL вашего Mini App: `https://ваш_домен.com/miniapp`

### 3. Установка webhook (опционально)

Если хотите, чтобы бот отвечал на сообщения:

```bash
# Установка webhook
curl "https://ваш_домен.com/telegram/set-webhook"

# Проверка webhook
curl "https://ваш_домен.com/telegram/webhook-info"
```

## 🔧 API Endpoints

### Mini App

- `GET /miniapp` - Главная страница Mini App
- `GET /miniapp/test` - Тестовый endpoint (без проверки подписи)
- `POST /miniapp/profile` - Получение профиля пользователя (с проверкой подписи)
- `POST /miniapp/debug` - Отладочная информация (с проверкой подписи)

### Telegram Bot

- `POST /telegram/webhook` - Webhook для получения сообщений от Telegram
- `GET /telegram/set-webhook` - Установка webhook
- `GET /telegram/webhook-info` - Информация о webhook
- `GET /telegram/delete-webhook` - Удаление webhook

## 🛡️ Безопасность

Приложение использует проверку подписи `initData` от Telegram для аутентификации пользователей. Middleware `VerifyTelegramInitData` проверяет:

1. Наличие `initData` в запросе
2. Корректность подписи с использованием токена бота
3. Целостность данных

## 🎨 Дизайн

- Адаптивный дизайн для мобильных устройств
- Поддержка светлой и темной темы Telegram
- Использование CSS переменных Telegram для интеграции с системой
- Современный Material Design

## 📱 Как использовать

1. Найдите вашего бота в Telegram
2. Отправьте команду `/start`
3. Нажмите кнопку "🚀 Открыть Mini App"
4. Просматривайте свой профиль и отладочную информацию

## 🐛 Отладка

Для отладки приложения:

1. Откройте Mini App в Telegram
2. Посмотрите раздел "🐛 Отладочная информация"
3. Проверьте раздел "⚙️ Telegram WebApp API"
4. Используйте Developer Tools браузера для дополнительной отладки

## 📄 Логи

Все запросы к API логируются в Laravel logs. Проверьте `storage/logs/laravel.log` для отладки проблем.

## 🔗 Полезные ссылки

- [Telegram Mini Apps Documentation](https://core.telegram.org/bots/webapps)
- [Telegram Bot API](https://core.telegram.org/bots/api)
- [Laravel Documentation](https://laravel.com/docs)

## 🚀 Развертывание

1. Настройте веб-сервер (Apache/Nginx)
2. Установите SSL сертификат (обязательно для Mini Apps)
3. Настройте домен в `.env` файле
4. Запустите `php artisan config:cache`
5. Настройте webhook бота (если нужно)

## 🎯 Следующие шаги

- [ ] Добавить базу данных для хранения пользователей
- [ ] Реализовать дополнительную функциональность
- [ ] Добавить push-уведомления
- [ ] Интеграция с платежами Telegram
