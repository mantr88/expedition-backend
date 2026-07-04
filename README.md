# Messenger Backend

Бекенд корпоративного месенджера. Laravel 13 · PHP 8.4 · PostgreSQL 16 · Redis 7 · Laravel Reverb · Sanctum (SPA cookie-based).

Плани та контракти: `messenger-backend-plan.md`, `messenger-api-contracts.md`, `messenger-architecture-plan.md` (у батьківському репозиторії документації).

## Швидкий старт (Docker)

```bash
cp docker/laravel-env.example .env
php artisan key:generate            # або: docker compose run --rm app php artisan key:generate
composer install                    # або: docker compose run --rm app composer install
docker compose up -d
docker compose exec app php artisan migrate --seed
```

Сервіси:

| Сервіс | Адреса | Призначення |
|---|---|---|
| nginx → php-fpm | http://localhost:8000 | REST API |
| reverb | ws://localhost:8080 | WebSocket (фаза B2) |
| postgres | localhost:5433 | БД (5433, щоб не конфліктувати з локальним) |
| redis | localhost:6380 | сесії, кеш, черги |
| node (vite) | http://localhost:5173 | dev-сервер фронт-активів |
| queue-worker | — | черги Redis |

Демо-логін: `anna@example.com` / `password` (сідер створює 60 користувачів, 5 каналів, повідомлення).

## Контракт фази B0 (для фронт-треку F0)

**Базовий URL API:** `http://localhost:8000` — REST під `/api`, auth-роути без префікса.

**Auth-флоу (Sanctum SPA, cookie-based):**

1. `GET /sanctum/csrf-cookie` → `204`, ставить `XSRF-TOKEN` cookie.
2. `POST /login` `{ email, password }` + заголовок `X-XSRF-TOKEN` → `204` (cookie-сесія) або `422`.
3. `GET /api/user` → `200` + `UserResource`; без сесії — `401`.
4. `POST /logout` → `204`; сесія інвалідується.

Запити фронта — з `credentials: include`; CORS дозволяє origin `FRONTEND_URL` (за замовчуванням `http://localhost:5173`).

**`UserResource` (базовий контракт, фіксується Pest-тестом `tests/Feature/Api/UserResourceContractTest.php`):**

```json
{
  "id": 1,
  "name": "Anna Petrenko",
  "email": "anna@example.com",
  "avatar_url": null,
  "status": "active",
  "last_seen_at": "2026-07-03T12:00:00.000000Z"
}
```

**Формати помилок:** `422 { message, errors: { field: [..] } }` · `401 { message }` · `403 { message }`.

## Розробка

```bash
vendor/bin/pint            # стиль
vendor/bin/phpstan analyse # статичний аналіз (larastan, level 6)
php artisan test           # Pest (локально — sqlite in-memory)
```

CI (GitLab): Pint + PHPStan + Pest проти postgres/redis-сервісів — див. `.gitlab-ci.yml`.

## Схема даних (ядро)

`users`, `channels`, `channel_members`, `messages` (треди через `parent_id`, soft delete, `client_message_id` для ідемпотентності), `attachments`, `reactions`, `mentions`. У `channels` і `channel_members` закладено nullable `workspace_id` під майбутню мультитенантність. Ключові індекси: `messages(channel_id, id)`, `messages(parent_id)`, унікальні `channel_members(channel_id, user_id)` та `reactions(message_id, user_id, emoji)`.
