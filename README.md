# Notification System

Scalable notification API: create, batch, list, get, and cancel notifications across **SMS**, **Email**, and **Push**. Sending to the provider runs asynchronously via a queue.

## How to build and run on your PC

**Requirements:** Docker and Docker Compose.

1. **Clone the repo and go to the project root:**
   ```bash
   cd notification
   ```

2. **Start all services:**
   ```bash
   docker compose up -d --build
   ```

3. **Run database migrations** (first time only; run inside the app container):
   ```bash
   docker compose exec app php artisan migrate --force
   ```
   If you see "Connection refused", wait 20–30 seconds for MySQL to start, then run the command again.

4. **Open the app:**
   - **API / app:** http://localhost:8080  
   - **API docs (Swagger):** http://localhost:8080/docs  
   - **phpMyAdmin:** http://localhost:8081 (user: `notification`, password: `secret`)

**Stop everything:**
```bash
docker compose down
```

**Reset DB and start over:**
```bash
docker compose down -v
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

## Architecture Overview

- **Create:** `POST /api/notifications` (single) and `POST /api/notifications/batch` (up to 1000). Records are created immediately; sending to the provider is queued.
- **List:** `GET /api/notifications` with filters (`batch_id`, `status`, `channel`, `from`, `to`) and pagination. Or `GET /api/batches/{batch_id}/notifications` to list by batch.
- **Get:** `GET /api/notifications/{id}` by UUID or numeric ID.
- **Cancel:** `DELETE /api/notifications/{id}` for pending notifications.
- **Idempotency:** Use `idempotency_key` when creating to avoid duplicate records.
- **Rate limiting:** Configurable max messages per second per channel (sms, email, push); uses Redis when `CACHE_STORE=redis`.
- **Observability:** `GET /api/metrics` (queue depth, notification counts, success/failure rates, latency), `GET /api/health` (database and cache). Correlation ID in `X-Correlation-ID` header and logs.

---

## API examples

**Create a single notification**
```bash
curl -X POST http://localhost:8080/api/notifications \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"to":"+905551234567","channel":"sms","content":"Hello World"}'
# 202 → { "id": "uuid", "status": "pending", "created_at": "..." }
```

**Create a batch**
```bash
curl -X POST http://localhost:8080/api/notifications/batch \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"notifications":[{"to":"+905551234567","channel":"sms","content":"Hi"},{"to":"user@example.com","channel":"email","content":"Welcome"}]}'
# 202 → { "batch_id": "uuid", "count": 2, "notifications": [...] }
```

**List by batch_id**
```bash
curl "http://localhost:8080/api/notifications?batch_id=YOUR_BATCH_UUID" -H "Accept: application/json"

**Get by ID**
```bash
curl http://localhost:8080/api/notifications/{uuid} -H "Accept: application/json"
```

**Cancel**
```bash
curl -X DELETE http://localhost:8080/api/notifications/{uuid} -H "Accept: application/json"
```

Optional fields when creating: `priority` (high, normal, low), `idempotency_key`, `scheduled_at` (ISO8601).

---

## Queue and priority

Notifications are sent to the provider by a queue worker. Priority is controlled by queue order:

- `high` → `notifications-high`
- `normal` → `notifications-normal`
- `low` → `notifications-low`

Run the worker so it drains high first, then normal, then low:

```bash
php artisan queue:work --queue=notifications-high,notifications-normal,notifications-low
```

---

## Provider (development)

**Why I use localhost to simulate the notification provider**

External services like [Webhook.site](https://webhook.site) enforce request limits. When you exceed them (e.g. during development or tests), you get blocked with “request limit exceeded” and the app can’t send notifications. To avoid that, I use a **local simulator** instead of Webhook.site during development: the app sends to its own `/api/webhook/forward` endpoint, which returns provider-style responses (accept/fail, delay) without hitting any external limit.

- **Docker:** Set `NOTIFICATION_WEBHOOK_URL=http://nginx/api/webhook/forward` in `.env`. The app then calls its own webhook-forward endpoint (no external provider, no rate limit).
- **Local (no Docker):** Use `NOTIFICATION_WEBHOOK_URL=http://localhost:3457/api/webhook/forward` so the app targets the same local simulator.
- **Production:** Set `NOTIFICATION_WEBHOOK_URL` to your real provider URL (e.g. Webhook.site or your own gateway) when you’re ready to send live traffic.

---

## Testing

Run all tests:

```bash
php artisan test
```

Or only the notification API tests:

```bash
php artisan test tests/Feature/NotificationApiTest.php
```

With Docker:

```bash
docker compose exec app php artisan test
```

Tests use an in-memory SQLite database; no extra setup needed.

---

## API documentation

- **Swagger UI:** http://localhost:8080/docs (when the app is running)
- **OpenAPI spec:** [openapi.yaml](openapi.yaml)

---

## License

MIT
