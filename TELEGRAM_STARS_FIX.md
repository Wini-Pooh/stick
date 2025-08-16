# 🤖 Исправление платежей Telegram Stars

## 🔧 Проблема
При нажатии на кнопку "Заплатить 1 ⭐" происходит таймаут - "время ответа от бота истекло". Это происходит потому, что webhook бота не настроен для обработки платежей Telegram Stars.

## ✅ Решение

### Шаг 1: Настройка webhook через веб-интерфейс

1. **Откройте в браузере**: `https://tg.sticap.ru/bot-admin`
2. **Нажмите кнопку**: "⭐ Установить Webhook для Stars"
3. **Проверьте результат**: должно появиться `"success": true` и `"ready_for_stars": true`

### Шаг 2: Проверка через команду (альтернативный способ)

Подключитесь к хостингу по SSH и выполните:

```bash
cd /path/to/your/project
php artisan bot:fix-webhook-stars
```

### 🔍 Что должно быть исправлено

**До исправления:**
```json
{
  "allowed_updates": ["message", "edited_message", "inline_query", "callback_query"]
}
```

**После исправления:**
```json
{
  "allowed_updates": [
    "message",
    "edited_message", 
    "callback_query",
    "inline_query",
    "pre_checkout_query",    // ← Критично для Stars!
    "successful_payment"     // ← Критично для Stars!
  ]
}
```

## 📋 Проверка работоспособности

### 1. Проверить webhook info
```bash
curl "https://api.telegram.org/bot8410914085:AAEkR3kyRw-lvb8WRP0MRQugvpEH-fkhLp4/getWebhookInfo"
```

### 2. Протестировать платеж
1. Откройте бота в Telegram
2. Нажмите "🎰 Играть в лото"
3. Купите билет за 1 ⭐
4. Нажмите "Заплатить ⭐️1"
5. **Ожидаемый результат**: быстрое открытие платежного окна без таймаута

### 3. Проверить логи
Логи платежей можно посмотреть в файле `/storage/logs/laravel.log`:

**Успешная последовательность:**
```
[INFO] 🌟 Pre-checkout query received
[INFO] ✅ Pre-checkout query approved  
[INFO] 🌟 Successful payment received
[INFO] ✅ Lotto ticket payment confirmed
```

## 🚨 Возможные проблемы и решения

### Проблема 1: "Invalid webhook URL"
**Причина**: Неправильный APP_URL в .env
**Решение**: Проверить что `APP_URL=https://tg.sticap.ru` в `.env`

### Проблема 2: Webhook не устанавливается
**Причина**: Проблемы с SSL сертификатом
**Решение**: Проверить что сайт доступен по HTTPS

### Проблема 3: Pre-checkout не приходит
**Причина**: Webhook не включает `pre_checkout_query`
**Решение**: Повторно установить webhook через веб-интерфейс

### Проблема 4: Successful payment не приходит
**Причина**: Webhook не включает `successful_payment`
**Решение**: Повторно установить webhook через веб-интерфейс

## 📞 Тестирование

После настройки протестируйте полный цикл платежа:

1. ✅ Отправка счета (уже работает по логам)
2. ✅ Нажатие кнопки "Заплатить" (должно быть быстрым)  
3. ✅ Pre-checkout query (проверить в логах)
4. ✅ Successful payment (проверить в логах)
5. ✅ Подтверждение в чате

## 🔗 Полезные ссылки

- **Админка бота**: https://tg.sticap.ru/bot-admin
- **Webhook info**: https://tg.sticap.ru/telegram/webhook-info
- **Документация Telegram Stars**: https://core.telegram.org/bots/payments-stars

## 💡 Примечания

- Все платежи в цифровых товарах **ДОЛЖНЫ** быть в Telegram Stars (XTR)
- `pre_checkout_query` - критично для предварительной проверки
- `successful_payment` - критично для подтверждения платежа
- Таймаут 10 секунд на ответ в `pre_checkout_query`
