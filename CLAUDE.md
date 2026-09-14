# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

Baby Monitor: an offline-friendly newborn-tracking PWA. Laravel 12 API (`backend/`) + a standalone Vue 3 SPA (`frontend/`) — two independent apps, not Inertia, talking to each other only over HTTP with cookie auth.

## Development commands

The app runs via Docker Compose; there is no bare-metal dev setup.

```
docker compose up --build          # start postgres, backend (php-fpm), nginx (:8000), vite dev server (:5174)
docker compose exec backend php artisan db:seed   # first run only — seeds dev accounts (see below)
docker compose exec backend php artisan migrate   # after adding a migration
docker compose exec backend php artisan tinker    # inspect/mutate data directly
docker compose logs -f frontend    # or `backend` — tail Vite/Laravel output
```

- Frontend (PWA): http://localhost:5174 — **not** Vite's default 5173, deliberately offset so it doesn't collide with an unrelated project that might already be using 5173 on the same machine.
- Backend API: http://localhost:8000
- Seeded dev logins (all password `password`, overridable via `SEED_USER_PASSWORD`/`SEED_CO_PARENT_PASSWORD`/`SEED_TEST_USER_PASSWORD` in `backend/.env`):
  - `hpbandara94@gmail.com` — primary caregiver
  - `vishmanthi@gmail.com` — second caregiver, shares the **same** baby as the primary account (multi-caregiver pivot, not a separate baby)
  - `test@example.com` — fully separate account + baby, safe to use for testing/poking around without touching the other two

**Windows/Git Bash gotcha**: MSYS path conversion mangles absolute Unix-style paths passed to `docker compose exec`. Prefix commands like `docker compose exec -T backend php -l /var/www/app/...` with `export MSYS_NO_PATHCONV=1;` or the path gets silently rewritten to something like `C:/Program Files/Git/var/www/...`.

### Tests

Backend has PHPUnit wired up but only the default Laravel skeleton tests exist (`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`) — no real coverage of the tracker endpoints yet.

```
docker compose exec backend php artisan test                      # full suite
docker compose exec backend php artisan test --filter=TestName    # single test
```

Frontend has no test runner configured (no vitest/jest). Frontend changes in this codebase have historically been verified by manually driving the app in a browser rather than automated tests.

### Lint

`laravel/pint` is a backend dev dependency but not wired into any script: `docker compose exec backend vendor/bin/pint`. No ESLint/Prettier config exists for the frontend.

## Architecture

### Auth: Sanctum SPA (cookie) auth, split across web.php and api.php

`/login` and `/logout` live in `routes/web.php` (session-based, CSRF-protected); every data endpoint lives in `routes/api.php` behind `auth:sanctum` + `$middleware->statefulApi()` (`bootstrap/app.php`). The frontend's single axios instance (`frontend/src/api/client.js`) calls `ensureCsrfCookie()` (hits `/sanctum/csrf-cookie` once, cached) before the first login/mutating request. Because dev/prod deploy the frontend and backend to different origins (Vercel + Render in prod; different ports in dev), `FRONTEND_URL`/`SANCTUM_STATEFUL_DOMAINS`/CORS config (`backend/config/cors.php`) are load-bearing, not boilerplate — cross-origin cookie auth breaks silently if any of the three drift out of sync.

### Multi-caregiver data model

A baby can have more than one caregiver (e.g. both parents) sharing the exact same records:
- `Baby` ↔ `User` is many-to-many through the `baby_user` pivot table. `babies.user_id` still exists (the original creator) but is no longer used for access control.
- `BabyScopedApiController` (`app/Http/Controllers/Api/BabyScopedApiController.php`) is the single place access control is enforced — every one of the 7 tracker controllers extends it and calls `ensureOwnsBaby()`/`ensureOwnsRecord()`, which check pivot membership, not `user_id` equality.
- Every tracker row also stores `created_by` (who logged it — distinct from "who is allowed to log for this baby"), exposed in API resources as `created_by`/`created_by_name` via a `creator` relation that controllers eager-load or set manually after insert (`$model->setRelation('creator', $request->user())`) to avoid an extra query on the hot path.

### The 7 trackers share one shape

Feeding, sleep, diaper, temperature, growth, medication, symptom. Each has: a migration, an Eloquent model, a `Store*Request` + `Update*Request` pair, an API Resource, and a controller extending `BabyScopedApiController`. All 7 are wired in `routes/api.php` by a single `foreach` over a `$trackers` map, generating **shallow** `apiResource` routes with only `index,store,update,destroy` (no `show` — the frontend never fetches a single record by id, only lists).

Two validation-request classes per tracker is deliberate, not duplication: `Store*Request` requires the full field set for a new record, while `Update*Request` mirrors the same rules with `sometimes` so a `PATCH` can be partial (e.g. `SleepSessionController`'s "end sleep" flow only sends `{ended_at, notes}`, never the original `started_at`).

Idempotency: every create carries a client-generated `client_uuid`; `store()` does `updateOrCreate(['client_uuid' => ...], $data)` so a retried/duplicate sync from a flaky connection can never create two rows for one tap. Separately, `findPossibleDuplicate()` (base controller) *soft-warns* (returns `possible_duplicate_of`, doesn't block) when another record of the same kind lands within ~2 minutes — this catches "logged the same feed twice by accident," not sync retries.

### Sleep is backend-synced; feeding is not (intentional asymmetry)

Sleep sessions can be "open" (`ended_at IS NULL`) to represent "asleep right now," and this state lives on the server specifically so every caregiver's device shows the same live timer without either of them doing anything. `SleepSessionController::store()` refuses to open a second concurrent session for the same baby — if one is already open, it hands back the existing row instead (this is what makes two caregivers racing to tap "start sleep" safe). The frontend (`SleepForm.vue`) POSTs a bare `{started_at}` with no `ended_at` to open a session and later `PATCH`es the same row's `ended_at` to close it — it never creates a second row for one nap.

Feeding's "active" state, by contrast, is tracked purely on-device (IndexedDB via `offline/db.js`'s `settings` store) and never touches the backend until the feed is completely finished — starting a feed is invisible to other caregivers until it's logged. This inconsistency exists because sleep's cross-caregiver visibility was added later as a specific fix; it has not (yet) been extended to feeding.

### Frontend data flow

- `frontend/src/constants/trackers.js` (`TRACKERS`) is the single source of truth for each tracker's label/emoji/color/time-field, shared by `HomeView`, `HistoryView`, and `ChartsView` so they can't drift out of sync with each other.
- `composables/useEntryLogger.js` is the shared **create** flow: generates a `client_uuid`, writes an optimistic local copy (`offline/db.js`), then POSTs if online or queues into IndexedDB (`offline/sync.js` flushes the queue on reconnect / every 30s) if not.
- `composables/useEntryEditor.js` is the parallel **edit** flow (`PATCH`), used by the same 7 form components when opened in "edit mode" via an optional `record` prop — it's online-only, there is no offline queue for edits.
- `HomeView.vue` re-fetches every tracker's latest entry every 2 minutes and on `visibilitychange`, not just on mount — without this, one caregiver's new entry (or a sleep they just started/ended) can leave a stale "overdue" alert or a stale live-sleep indicator on the other caregiver's screen.
- History's edit UI reuses the same 7 create-forms rather than separate edit forms: passing a `record` prop switches a form's internal behavior (pre-fills fields, submits via `useEntryEditor` instead of `useEntryLogger`). `FeedingForm`/`SleepForm` branch to a dedicated simple edit template that bypasses their live start/end-session UI entirely.

### Styling

One global stylesheet, `frontend/src/style.css` — no component ever uses a scoped `<style>` block. Theming is CSS custom properties on `:root`, overridden under `:root[data-theme='dark']`; light is the default and `useTheme.js` persists the user's choice to `localStorage`.

### Known dev-environment quirks worth not re-discovering

- Docker Desktop on Windows doesn't forward native filesystem events across the bind mount, so Vite's dev server needs `usePolling: true` (`frontend/vite.config.js`) or it never sees host-side file edits.
- `crypto.randomUUID()` throws outside a secure context (e.g. serving the app over plain HTTP from a LAN IP instead of `localhost`); `frontend/src/utils/uuid.js` falls back to `crypto.getRandomValues()`. Always import the UUID generator from there rather than calling `crypto.randomUUID()` directly.

## Deployment

Dev is Docker Compose end-to-end; production is three separate services, not the same stack:
- **Neon** (Postgres) — connection string passed as `DB_URL`.
- **Render** — `render.yaml` + `backend/Dockerfile.render` (single-container nginx+PHP-FPM; Render doesn't support the two-container dev split). Migrations run automatically on every deploy; seeding is a manual one-time `php artisan db:seed` via Render's Shell tab.
- **Vercel** — frontend static build, not yet wired up (`VITE_API_URL` must point at the Render URL).

See the root `README.md` for the full step-by-step.
