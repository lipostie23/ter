# SECURITY — действия после security-аудита

## ⚠️ Что ОБЯЗАТЕЛЬНО сделать ВРУЧНУЮ (я этого сделать за тебя не могу)

Все credentials лежали в публичном GitHub-репо в коммитах. Это значит, что они УЖЕ
скомпрометированы (боты сканируют public github постоянно). Их нужно ротировать.

### 1. Сменить пароль БД `gs339375`
Через панель хостера или `mysql -e "SET PASSWORD..."`.

### 2. Перевыпустить секрет Platega
В личном кабинете <https://app.platega.io>: пересоздай мерчанта или сбрось secret.
Старый secret `MFknNG5SU3gB...` уже скомпрометирован.

### 3. Пересоздать оба Telegram-бота
Через @BotFather → `/revoke`, потом `/token`. Это касается:
- `8700184412:AAEsondY...` (бот для уведомлений о донатах из webhook'а)
- `8670784710:AAGCD5qS...` (бот связки игры с Telegram, в site.php)

### 4. Сменить `game_ingest_token`
Старый `Lgi_g9K4mPvq2NwX8bRtz1YhFc0JsAe6UdBo7` уже публичен.
Сгенерируй новый, например:
```bash
openssl rand -base64 32 | tr -d /+= | cut -c1-40
```
И пропиши его И в `config.local.php`, И в игровом моде, который шлёт запросы.

### 5. Создать `config.local.php` рядом с `config.php`
Этот файл уже в `.gitignore` и НЕ попадает в git. Скелет:
```php
<?php
$config['db']['pass']                    = 'НОВЫЙ_ПАРОЛЬ_БД';
$config['platega']['secret']             = 'НОВЫЙ_СЕКРЕТ_PLATEGA';
$config['telegram_bot']['token']         = 'НОВЫЙ_ТОКЕН_TG_БОТА';
$config['security']['game_ingest_token'] = 'НОВЫЙ_GAME_TOKEN';

// Опционально — токен для /site.php?debug_io=1&token=...
// Пусто = endpoint выключен полностью.
$config['security']['debug_token']       = 'случайная_строка_или_оставь_пустым';
```
И отредактируй `config.php`: замени реальные пароли на placeholder'ы или пустые
строки (значения всё равно перетрутся из `config.local.php`).

### 6. Удалить старые credentials из истории git (опционально, но желательно)
```bash
# Через BFG repo-cleaner (быстрее всего):
bfg --replace-text replacements.txt
git reflog expire --expire=now --all
git gc --prune=now --aggressive
git push --force-with-lease
```
Где `replacements.txt` содержит строки старых паролей. После этого старая ветка
с раскрытыми секретами будет удалена с GitHub.

---

## Что чинит этот PR автоматически

| # | Дыра | Фикс |
|---|---|---|
| 1 | `/logs/` доступен по HTTP — утечка донатов, TG-сообщений | `logs/.htaccess: Require all denied` |
| 2 | `/cache/` доступен по HTTP — утечка результатов рулетки | `cache/.htaccess: Require all denied` |
| 3 | `site.php?debug_io=1` без auth — info disclosure | Требует `&token=` совпадающий с `$config['security']['debug_token']`, иначе 404. По умолчанию endpoint выключен. |
| 4 | `site.php?type=2&chatId=...&nickname=...` — фишинг через свой домен | Прямой GET-вход проверяет `?token=` или заголовок `X-Game-Token`. Behaviour опционально через `$config['security']['allow_legacy_game_get']`. |
| 5 | DoS-усиление через `online.php` | Файл-кэш на 5 секунд. |
| 6 | Race conditions при записи JSON-логов | `flock(LOCK_EX)` на read+write. |

---

## ⚠️ ВАЖНО: миграция `site.php`

В новом `config.php` я выставил `'allow_legacy_game_get' => true` — старый GET-поток
работает как и раньше, чтобы НЕ сломать твой текущий игровой мод.

После того как ты обновишь мод, чтобы он передавал `&token=ТВОЙ_GAME_TOKEN`
(или заголовок `X-Game-Token: ...`) — **переключи флаг в `false`** в `config.local.php`:
```php
$config['security']['allow_legacy_game_get'] = false;
```
Это закроет фишинг-вектор окончательно.

В логе `webhook.log` все вызовы legacy-режима теперь маркируются строками
`legacy_game_get_allowed ip=... qs=...` — посмотри, что с какого IP ходит мод,
чтобы при необходимости добавить IP-whitelist на уровне веб-сервера.

---

## Nginx-конфиг (если у тебя nginx, .htaccess не сработает)

```nginx
location ~ ^/(logs|cache)/ { deny all; return 404; }
location ~ /\.ht          { deny all; }
location ~ /(config\.php|config\.local\.php|partials\.php|site\.php\.bak)$ { deny all; }
```
