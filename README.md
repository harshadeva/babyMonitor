# Baby Monitor

Fast, offline-friendly newborn tracking. Laravel API (Postgres) + Vue 3 PWA frontend.

## Stack

- **Backend**: Laravel 12, PostgreSQL, Sanctum (SPA cookie auth), served by nginx + PHP-FPM
- **Frontend**: Vue 3 (standalone SPA, Vue Router, Pinia), Vite PWA plugin, Dexie (IndexedDB) for offline queueing, Chart.js
- **Infra**: Docker Compose (postgres, backend [php-fpm], webserver [nginx], frontend)

## Run it

```
docker compose up --build
```

- Frontend (PWA): http://localhost:5173
- Backend API: http://localhost:8000

First time only — seed a login and a baby record:

```
docker compose exec backend php artisan db:seed
```

Default login (change `SEED_USER_PASSWORD` in `backend/.env` before seeding if you want a different one):

- Email: `hpbandara94@gmail.com`
- Password: `password`

A second, separate account + baby is also seeded for testing/poking around, so it never touches the real data above (override via `SEED_TEST_USER_EMAIL`/`SEED_TEST_USER_PASSWORD`):

- Email: `test@example.com`
- Password: `password`

## Deploying (Neon + Render + Vercel)

Dev uses Docker Compose with a local Postgres container; production uses **Neon** (Postgres), **Render** (Laravel API), and **Vercel** (frontend static build) — three separate free-tier services, not this same docker-compose stack.

### 1. Database — Neon

Create a project at [neon.tech](https://neon.tech), then copy its connection string (starts `postgresql://...?sslmode=require`) — you'll paste it as `DB_URL` in Render below.

### 2. Backend API — Render

This repo includes `render.yaml` and `backend/Dockerfile.render` (a single-container nginx+PHP-FPM image — Render runs one container per service, unlike the two-container dev setup).

1. On [render.com](https://render.com), **New → Blueprint**, connect this GitHub repo. Render reads `render.yaml` automatically and creates the `babymonitor-api` web service.
2. Fill in the env vars marked "generate/set in dashboard" in `render.yaml`:
   - `APP_KEY` — generate one locally: `docker compose exec backend php artisan key:generate --show` (copy the `base64:...` output — don't reuse the dev one).
   - `APP_URL` — Render gives you the service URL after first deploy (`https://babymonitor-api-xxxx.onrender.com`); update this var and redeploy once you know it.
   - `DB_URL` — the Neon connection string from step 1.
   - `FRONTEND_URL` / `SANCTUM_STATEFUL_DOMAINS` — your Vercel URL once deployed (step 3). Leave blank for the first deploy; login won't work cross-origin until these are set correctly, so come back and fill these in.
   - `SEED_USER_EMAIL` / `SEED_USER_PASSWORD` — your real login for production (not the dev default).
3. Deploy runs migrations automatically on every container start (`backend/docker/render/entrypoint.sh`). Seeding is a manual one-time step — after the first successful deploy, open Render's **Shell** tab for the service and run `php artisan db:seed`.
4. Free tier spins the service down after ~15 minutes idle; the first request after that takes 30–60s to wake it back up. Fine for a personal app, just don't be alarmed by the first cold load.

### 3. Frontend — Vercel

Not wired up yet — when ready: import the repo into Vercel, set the project root to `frontend/`, and set `VITE_API_URL` to the Render URL from step 2.

## How data flows

- All 7 trackers (feeding, sleep, diaper, temperature, growth, medication, symptom) live under `/api/babies/{baby}/{tracker}`.
- Every create carries a client-generated `client_uuid`. The server upserts on `(baby_id, client_uuid)`, so a retried/duplicate sync from a flaky connection can never create two rows for the same tap.
- The frontend queues creates in IndexedDB (via Dexie) when offline and flushes them in the background once the connection returns — logging never blocks on network.
- Breastfeeding and sleep use a local "start" (no network call) then a "end" that posts the complete record — so starting a feed/sleep session works even with zero connectivity.
- Diaper stool color and newborn temperature both carry lightweight clinical flags (`flagged_for_doctor`, fever threshold) surfaced as a toast — not diagnosis, just "mention this at the next visit."

## Data model note

Every record is scoped to a `baby_id`, even though v1's UI only ever shows one baby — this was a deliberate choice so a future multi-child/multi-family version doesn't need a schema rewrite.

## Troubleshooting: "Could not sign in" / requests failing only in the browser

If `curl http://localhost:8000/up` works but the app's login always fails in Chrome specifically (page navigation to `:8000/up` works, but `fetch`/XHR calls from the page don't), a browser extension is very likely blocking script-initiated requests to `localhost:8000` (ad-blockers and privacy/dictionary extensions commonly do this). Confirm by testing in an Incognito window (extensions off by default) or by disabling extensions one at a time.

## What's intentionally out of v1

Hardware/sensor integration, multi-caregiver accounts, automated alerts beyond the two clinical flags above, pattern/correlation analysis, PDF export. The data model (structured, quantified fields) is built so these can be added later without re-collecting data.
