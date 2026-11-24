# CargoTrace: Global Shipment Visibility & Tracking Platform

CargoTrace gives logistics companies and their customers one place to follow shipments from origin to destination.
Operators register shipments, add tracking events, and manage exceptions. The system applies explicit, testable
rules to detect delays, and customers can see status, ETA, the route on a map and the full event timeline.

Built with **Laravel 12, MySQL, Blade, Tailwind CSS, vanilla JavaScript (Fetch API), Leaflet, Chart.js and Laravel Sanctum**
for the *Web Programming 2* final project (UAS).

> Demo data is **synthetic** (fictional carriers and customers, approximate port coordinates) but internally consistent.
> See [docs/KNOWN_LIMITATIONS.md](docs/KNOWN_LIMITATIONS.md).

## The problem

* Shipment information is scattered and hard to monitor.
* Customers do not know the latest status or journey history.
* Delays and exceptions are not visible in one place.
* Operators need a dashboard to prioritise follow-up work.

## Features

| Area | What it does |
|------|--------------|
| Shipment lifecycle | `CREATED → BOOKED → PICKED_UP → IN_TRANSIT → DELIVERED` (+ `CANCELLED` from CREATED/BOOKED). Every transition is validated on the backend; `DELIVERED` and `CANCELLED` are terminal. |
| Tracking & timeline | Append-only tracking events with time, location and coordinates; chronological timeline; exception events highlighted; current location = newest event with a valid position. |
| ETA & delay rules | Deterministic rules. Delayed when ETA > expected arrival. Severity by delay duration with thresholds per trade lane (Group A/B/C) or global. The UI shows the rule set and the values used. |
| Trade lanes (Groups A, B, C) | One application serves all three group scenarios: per-lane route, delay threshold, main exception and KPI, with a comparison table on the dashboard. |
| Exceptions | `CUSTOMS_HOLD`, `WEATHER_DELAY`, `PORT_CONGESTION`, `DAMAGED`, `MISROUTED`, `DOCUMENTATION_ISSUE`; severity; `OPEN → INVESTIGATING → RESOLVED`; detection and resolution times stored. |
| Map | Leaflet + OpenStreetMap with origin, destination and tracking-point markers, popups and attribution. |
| External APIs | Nominatim geocoding (locations form) and Open-Meteo weather (shipment page) via Laravel HTTP Client with timeout/error/empty handling. Weather is shown as context only, never as a delay prediction. |
| Dashboard | Live database metrics: totals, in transit, delivered, delayed, open exceptions, by status, by carrier, delay and exception rate, plus the three group KPIs. Charts via Chart.js. |
| Search & filter | Shipment number, status, carrier, origin, destination, date range, delay state, sorting and server-side pagination (web and API). |
| Notifications & audit | In-app notifications (plus optional e-mail copies via `NOTIFY_BY_EMAIL=true`); audit log with actor, action, time, entity and old/new values. |
| Roles | Administrator, Logistics Operator, Customer, enforced by middleware and policies on every route and endpoint. |
| REST API | JSON, Sanctum tokens, validation, pagination, consistent errors, Postman collection. |

## Screenshots

Add your own screenshots to `docs/screenshots/` (see the list in that folder's README) and uncomment the block below.

<!--
![Dashboard](docs/screenshots/dashboard.png)
![Shipment detail with map and timeline](docs/screenshots/shipment-detail.png)
![Shipments list with filters](docs/screenshots/shipments-list.png)
-->

## Roles

| Role | Can do |
|------|--------|
| **Administrator** | Everything: users, settings (delay thresholds), carriers, locations, all shipments, audit log, delete shipments. |
| **Logistics Operator** | See all shipments, create/update shipments (not once terminal), add tracking events, manage exceptions. Cannot delete shipments or manage users/reference data. |
| **Customer** | Read-only access to their own shipments: status, ETA, timeline, map, notifications, relevant exceptions. |

Demo accounts created by the seeder (password from `DEMO_PASSWORD`, default `password`):

| Email | Role |
|-------|------|
| `admin@cargotrace.test` | Administrator |
| `operator@cargotrace.test` | Logistics Operator |
| `customer@cargotrace.test` | Customer |

## Getting started

Requirements: PHP 8.2+ (8.3 recommended) with `pdo_mysql`, `mbstring`, `bcmath`; Composer 2; MySQL 8+; Node 20+ (optional, for compiling Tailwind).

```bash
composer install
cp .env.example .env
php artisan key:generate

# create an empty MySQL database named "cargotrace" and put the credentials in .env
php artisan migrate --seed

npm install && npm run build     # optional: without it the app falls back to the Tailwind browser build
php artisan serve                # http://localhost:8000
```

No MySQL handy? Use SQLite for a quick look:

```bash
touch database/database.sqlite
# in .env: DB_CONNECTION=sqlite  (remove the other DB_* lines)
php artisan migrate --seed
```

Check your setup at any time (PHP, key, database, demo data, thresholds, geocoding and weather services):

```bash
php artisan cargotrace:doctor
```

Optional scheduler (re-evaluates delays of in-flight shipments every hour):

```bash
php artisan schedule:work                 # development
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1   # production cron
```

### Group scenarios A, B and C in one application

The brief gives every group its own parameters. CargoTrace serves **all three at the same time** through *trade lanes*:
each shipment belongs to a lane, and each lane carries its own route, delay threshold, main exception and KPI.

| Lane | Route | Delay threshold (MEDIUM up to / HIGH above) | Main exception | Highlighted KPI |
|------|-------|---------------------------------------------|----------------|-----------------|
| **A** | Southeast Asia → Europe | 12 h (LOW up to 6 h) | `CUSTOMS_HOLD` | on-time delivery rate |
| **B** | Asia → North America | 18 h (LOW up to 9 h) | `WEATHER_DELAY` | average delay duration |
| **C** | Southeast Asia → Australia | 24 h (LOW up to 12 h) | `DOCUMENTATION_ISSUE` | exception resolution time |

* The seeder creates all three lanes with 22 shipments each (`GROUP_SCENARIO=all`, the default; use `a`, `b` or `c` for a single lane).
* The dashboard shows a **Trade lane performance** table (thresholds, main exception, the three KPIs, delayed shipments, open exceptions per lane), a lane filter, and highlights each group's own KPI.
* The shipment list can be filtered by lane; the shipment form has a *Trade lane* field; the exception form preselects the lane's main exception.
* Delay severity uses the shipment's lane thresholds. A shipment without a lane uses the global thresholds (`DELAY_*` in `.env` or the Settings page).
* An administrator edits every lane (thresholds, main exception, KPI) on the **Settings** page; changes are audited.
* Scenario data (carriers, locations, routes) lives in `database/seeders/scenarios/{a,b,c}.php`; copy one to create your own variation.

After pulling this version run `php artisan migrate:fresh --seed` (the `shipments` table gained `trade_lane_id`).

## Delay rules

```
effective arrival = actual arrival                      (delivered)
                  = ETA                                  (if recorded)
                  = expected arrival                     (otherwise)
if the shipment is PICKED_UP/IN_TRANSIT and "now" is past the effective arrival,
   effective arrival = now                               (it is at least that late)

delayed  = effective arrival > expected arrival
delay    = effective arrival − expected arrival (hours)
severity = LOW     if delay ≤ low_max_hours      (lane value, else global default 6)
           MEDIUM  if delay ≤ medium_max_hours   (lane value 12 / 18 / 24, else global default 24)
           HIGH    otherwise
```

Implemented in `app/Services/EtaService.php`, covered by `tests/Feature/EtaDelayTest.php`.

## Running the tests

The suite uses an in-memory SQLite database, so MySQL is not needed:

```bash
php artisan test
# or
composer test
```

Coverage areas: authentication (valid/invalid login, unauthenticated access), shipment CRUD, validation and authorization,
tracking events and invalid status transitions, ETA/delay rules, exceptions, external API success/timeout/error/empty cases,
dashboard numbers, role-based web access. See [docs/TEST_EVIDENCE.md](docs/TEST_EVIDENCE.md).

## REST API

Base path `/api`, JSON in/out, Bearer token from `POST /api/login`.

| Method | Endpoint | Notes |
|--------|----------|-------|
| POST | `/api/register`, `/api/login`, `/api/logout` | register always creates a *customer* |
| GET | `/api/user` | current user |
| GET/POST | `/api/shipments` | filters, sorting, pagination; create = admin/operator |
| GET/PUT/PATCH/DELETE | `/api/shipments/{id}` | delete = admin |
| GET/POST | `/api/shipments/{id}/tracking` | timeline + current location / add event |
| GET/POST | `/api/shipments/{id}/exceptions` | |
| PATCH | `/api/exceptions/{id}` | status flow, resolution notes |
| GET | `/api/trade-lanes` | Groups A, B, C with their parameters |
| GET/POST | `/api/carriers`, `/api/locations` | POST = admin |

Status codes: `200/201` success, `401` unauthenticated, `403` forbidden, `404` not found, `422` validation, `429` throttled.
Errors never include stack traces. Full details: [docs/API.md](docs/API.md) · Postman: [docs/postman/CargoTrace.postman_collection.json](docs/postman/CargoTrace.postman_collection.json).

## Architecture in one picture

```
Browser ─▶ Routes + Middleware ─▶ Controllers ─▶ Service layer ─▶ Eloquent models ─▶ MySQL
                                   (HTTP only)    ShipmentService      │
                                                  TrackingService      └─▶ HTTP Client ─▶ Nominatim / Open-Meteo
                                                  EtaService
                                                  ExceptionService
```

Controllers handle HTTP; services own business rules and transactions; policies own authorization; form requests own validation.
Details and diagrams: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), [docs/ERD.md](docs/ERD.md), [docs/DATA_DICTIONARY.md](docs/DATA_DICTIONARY.md).

## Security notes

Password hashing (bcrypt), auth middleware, server-side role checks and policies, form-request validation, CSRF tokens on
all web forms, Blade output escaping (XSS), Eloquent parameter binding (SQL injection), mass-assignment protection
(`role`, `status`, delay fields are not fillable), login throttling, API tokens revoked on logout/deactivation,
`.env` excluded from Git, API keys read from the environment only.

## Deployment

Docker Compose (`docker compose up --build`) and VPS instructions are in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
CI (`.github/workflows/ci.yml`) runs the test suite and the asset build on every push.

**Live demo:** _add your URL here_ · **Demo account:** `customer@cargotrace.test` / _password_

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `could not find driver` | Enable the `pdo_mysql` PHP extension (Laragon: right-click → PHP → Extensions). |
| `Access denied for user` / `Unknown database` | Check `DB_*` in `.env` and create the empty `cargotrace` database. |
| Cannot log in | Run `php artisan migrate:fresh --seed`; accounts and password are listed above and in `docs/DEMO_DATA.md`. |
| Blank or 500 page after changing files or `.env` | `php artisan optimize:clear` |
| Page without styling | No internet for the Tailwind CDN fallback: run `npm install && npm run build`. |
| `/api/...` in the browser shows "Unauthenticated." | Expected: the API needs a Bearer token (use Postman). |

## Project documentation

* [ERD](docs/ERD.md) · [Data dictionary](docs/DATA_DICTIONARY.md) · [Architecture](docs/ARCHITECTURE.md)
* [API reference](docs/API.md) · [Postman collection](docs/postman/CargoTrace.postman_collection.json)
* [Test evidence](docs/TEST_EVIDENCE.md) · [Deployment](docs/DEPLOYMENT.md)
* [Requirements traceability](docs/REQUIREMENTS_TRACEABILITY.md) · [Demo data](docs/DEMO_DATA.md)
* [Known limitations & future work](docs/KNOWN_LIMITATIONS.md) · [Attribution](docs/ATTRIBUTION.md)

## License

MIT (course project).
