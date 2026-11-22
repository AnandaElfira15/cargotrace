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
| ETA & delay rules | Deterministic rules. Delayed when ETA > expected arrival. Severity by delay duration (thresholds configurable). The UI shows the rule and the values used. |
| Exceptions | `CUSTOMS_HOLD`, `WEATHER_DELAY`, `PORT_CONGESTION`, `DAMAGED`, `MISROUTED`, `DOCUMENTATION_ISSUE`; severity; `OPEN → INVESTIGATING → RESOLVED`; detection and resolution times stored. |
| Map | Leaflet + OpenStreetMap with origin, destination and tracking-point markers, popups and attribution. |
| External APIs | Nominatim geocoding (locations form) and Open-Meteo weather (shipment page) via Laravel HTTP Client with timeout/error/empty handling. Weather is shown as context only, never as a delay prediction. |
| Dashboard | Live database metrics: totals, in transit, delivered, delayed, open exceptions, by status, by carrier, delay and exception rate, plus the three group KPIs. Charts via Chart.js. |
| Search & filter | Shipment number, status, carrier, origin, destination, date range, delay state, sorting and server-side pagination (web and API). |
| Notifications & audit | In-app notifications; audit log with actor, action, time, entity and old/new values. |
| Roles | Administrator, Logistics Operator, Customer, enforced by middleware and policies on every route and endpoint. |
| REST API | JSON, Sanctum tokens, validation, pagination, consistent errors, Postman collection. |

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

Optional scheduler (re-evaluates delays of in-flight shipments every hour):

```bash
php artisan schedule:work                 # development
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1   # production cron
```

### Group scenario (unique per group)

Each group receives its own parameters. Everything is configuration, no code changes needed:

| Setting | Where | Example (Group A) |
|---------|-------|-------------------|
| Delay threshold (hours) | `DELAY_MEDIUM_MAX_HOURS` in `.env` (or **Settings** page as admin) | `12` |
| KPI highlighted on dashboard | `GROUP_KPI` | `on_time_delivery_rate` |
| Main exception type | `GROUP_PRIMARY_EXCEPTION` | `CUSTOMS_HOLD` |
| Demo data set (carriers, locations, routes) | `GROUP_SCENARIO` → `database/seeders/scenarios/{a,b,c}.php` | `a` |

To switch scenario: edit `.env`, then `php artisan migrate:fresh --seed`. Copy a scenario file to create your own variation.

## Delay rules

```
effective arrival = actual arrival                      (delivered)
                  = ETA                                  (if recorded)
                  = expected arrival                     (otherwise)
if the shipment is PICKED_UP/IN_TRANSIT and "now" is past the effective arrival,
   effective arrival = now                               (it is at least that late)

delayed  = effective arrival > expected arrival
delay    = effective arrival − expected arrival (hours)
severity = LOW     if delay ≤ low_max_hours      (default 6)
           MEDIUM  if delay ≤ medium_max_hours   (default 24, group value e.g. 12)
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

## Project documentation

* [ERD](docs/ERD.md) · [Data dictionary](docs/DATA_DICTIONARY.md) · [Architecture](docs/ARCHITECTURE.md)
* [API reference](docs/API.md) · [Postman collection](docs/postman/CargoTrace.postman_collection.json)
* [Test evidence](docs/TEST_EVIDENCE.md) · [Deployment](docs/DEPLOYMENT.md)
* [Known limitations & future work](docs/KNOWN_LIMITATIONS.md) · [Attribution](docs/ATTRIBUTION.md)

## License

MIT (course project).
