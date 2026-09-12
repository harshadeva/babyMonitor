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
