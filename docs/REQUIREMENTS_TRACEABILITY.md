# Requirements traceability

Maps every requirement of the course brief (UAS final project brief, 25 slides) to its implementation and its automated test.
Statuses: **Done** = implemented and covered by automated tests, **Done (manual)** = implemented, verified in the browser,
**Out of scope** = explicitly excluded by the brief.

## Scope (slide 4)

| Item | Status |
|------|--------|
| Shipment lifecycle, tracking timeline and map, ETA/delay rules, exceptions, RBAC, REST API, external API, dashboard, testing, deployment | Done (see below) |
| Integration with real ports, customs or carriers | Out of scope |
| **Financial payments / real commercial transactions** | **Out of scope: no payment feature exists, by design** |
| Real-time GPS from physical devices | Out of scope |
| Mandatory machine learning / AI prediction | Out of scope (delay detection is rule based; weather is informational only) |
| Complex microservices | Out of scope (layered monolith) |

## Roles (slide 5)

| Requirement | Implementation | Test |
|-------------|----------------|------|
| Admin: users and configuration, carriers and locations, all shipments, audit log | `UserController`, `SettingController`, `CarrierController`, `LocationController`, `AuditLogController`, `role:admin` routes | `WebTest::test_role_based_access_is_enforced_on_the_server`, `WebActionsTest` (users, settings, carriers, locations, audit) |
| Operator: create/update shipments, add tracking, manage exceptions | `ShipmentPolicy`, `role:admin,operator` routes | `ShipmentApiTest`, `TrackingApiTest`, `WebActionsTest` |
| Customer: own shipments, status, ETA, timeline, map, notifications, relevant exceptions | `Shipment::scopeVisibleTo`, `ShipmentPolicy::view` | `ShipmentApiTest::test_customer_only_sees_own_shipments`, `WebActionsTest::test_customer_list_shows_only_their_own_shipments` |
| Authorization on the server for all routes and endpoints | middleware + policies on web and API | `WebTest`, `ShipmentApiTest`, `ExceptionApiTest`, `TrackingApiTest` |

## Shipment lifecycle (slide 6)

| Requirement | Implementation | Test |
|-------------|----------------|------|
| Valid status flow, validated in the backend | `ShipmentStatus::allowedTransitions()`, `TrackingService::record` | `StatusRulesTest`, `TrackingApiTest::test_invalid_status_transition_is_rejected_and_nothing_is_stored` |
| Terminal status cannot go back | `ShipmentStatus::isTerminal()` | `TrackingApiTest::test_terminal_status_cannot_go_back_to_an_earlier_status` |
| Status is not free input | `status` not mass assignable, only changed by `TrackingService` | `ShipmentApiTest::test_status_cannot_be_set_through_create` |
| Minimum shipment data | `shipments` table, `StoreShipmentRequest` | `ShipmentApiTest::test_create_validates_required_fields_and_business_rules` |

## Tracking and timeline (slide 7)

| Requirement | Implementation | Test |
|-------------|----------------|------|
| Historical events, never overwritten | append-only `tracking_events`, `TrackingService` | `TrackingApiTest::test_history_is_append_only_and_current_location_follows_the_newest_valid_event` |
| Event fields (shipment, type/status, time, location, lat/lng, description, creator) | `tracking_events` migration | `TrackingApiTest` |
| Time-order and relation validation | `TrackingService` | `TrackingApiTest::test_event_time_must_not_be_earlier_than_the_latest_event_or_in_the_future`, `WebActionsTest::test_event_in_the_same_minute_as_the_previous_one_is_accepted` |
| Chronological timeline, normal vs exception events | `TrackingService::timeline`, `shipments/show.blade.php` | `TrackingApiTest::test_full_valid_lifecycle_is_recorded_in_order`, `ExceptionApiTest` |
| Current location from the newest valid event | `TrackingService::currentLocation` | `TrackingApiTest` |
| Customers only see their own shipments | policy | `TrackingApiTest::test_customer_cannot_add_events_but_can_read_own_timeline_only` |

## ETA, delay, exceptions (slide 8)

| Requirement | Implementation | Test |
|-------------|----------------|------|
| ETA vs expected arrival, delay duration, delayed flag | `EtaService::calculate` | `EtaDelayTest` (8 boundary cases, past-ETA rule, delivered shipments) |
| LOW 0-6 h, MEDIUM >6-24 h, HIGH >24 h | `EtaService::classify` | `EtaDelayTest::test_delay_and_severity_rules` |
| Threshold per group, configurable | trade lanes (`trade_lanes` table), global fallback in `.env` / `settings` table, Settings page | `TradeLaneTest::test_each_group_applies_its_own_delay_threshold`, `TradeLaneTest::test_admin_edits_the_parameters_of_each_lane`, `EtaDelayTest::test_threshold_is_configurable_per_group` |
| Show the reason and values used | `DelayResult::explanation()`, "ETA & delay rule" card | `EtaDelayTest::test_explanation_contains_the_values_used` |
| Exception types, severity, OPEN/INVESTIGATING/RESOLVED, detection and resolution time | `ExceptionService`, enums | `ExceptionApiTest`, `WebActionsTest::test_operator_reports_and_resolves_an_exception_through_the_web` |

## Map and external API (slide 9)

| Requirement | Implementation | Test |
|-------------|----------------|------|
| Leaflet + OSM, markers, popups, attribution | `shipments/show.blade.php` | Done (manual); page contains the map container (`WebActionsTest`) |
| Consistent coordinates | `decimal(10,7)` everywhere, event coordinates default from the location | `TrackingApiTest` |
| Geocoding and weather via HTTP Client; timeout, error and empty handling | `GeocodingService`, `WeatherService` | `ExternalApiTest` (success, empty, timeout, HTTP 500, unusable payload, authorization) |
| API keys in environment variables | `config/cargotrace.php` reads `.env` only | n/a (`.env` is git-ignored) |
| Weather is not a delay prediction | wording in `WeatherService` and UI | n/a |

## Group scenarios (slides 20, 23 and 25)

| Requirement | Implementation | Test |
|-------------|----------------|------|
| Each group has its own delay threshold, route, exception and KPI (Groups A, B, C) | `TradeLane` model, `EtaService::thresholds()`, `DashboardService::lanes()`, scenario files `database/seeders/scenarios/{a,b,c}.php` | `TradeLaneTest` (17 tests: per-lane severity, fallback, API, filter, dashboard breakdown, settings, seeder) |
| Different seed data per group | seeder creates 3 lanes × 22 shipments | `TradeLaneTest::test_the_demo_seeder_builds_groups_a_b_and_c_together` |
| Threshold can be changed during the interview | Settings page (global and per lane), `php artisan cargotrace:evaluate-delays` | `WebActionsTest`, `TradeLaneTest` |

## Dashboard, search, notifications (slide 10)

| Requirement | Implementation | Test |
|-------------|----------------|------|
| Dashboard metrics from the database | `DashboardService`, Chart.js | `DashboardTest` |
| Search, filter, sort, server-side pagination | `Shipment::scopeFilter`, `ShipmentFilterRequest` | `ShipmentApiTest::test_search_filter_sort_and_pagination_work_on_the_server`, `WebActionsTest::test_shipment_list_filters_by_status_and_delay` |
| In-app notifications | `NotificationService` | `WebTest`, `WebActionsTest` |
| E-mail notifications (advanced) | `ShipmentNotificationMail`, `NOTIFY_BY_EMAIL` | `EmailNotificationTest` |
| Audit log (actor, action, time, entity) | `AuditService` | `WebActionsTest::test_audit_log_filters_work` |

## Data model (slide 11)

| Requirement | Implementation |
|-------------|----------------|
| users, shipments, shipment_items, carriers, locations, tracking_events, shipment_exceptions, notifications, audit_logs | `database/migrations`, documented in [ERD.md](ERD.md) and [DATA_DICTIONARY.md](DATA_DICTIONARY.md) |
| Foreign keys, unique constraints, indexes, timestamps, soft delete only when needed | migrations (soft delete on `shipments` only, reasons in the ERD document) |
| Own ERD with cardinality and design decisions | [ERD.md](ERD.md) |

## REST API (slides 12 and 13)

| Requirement | Implementation | Test |
|-------------|----------------|------|
| All mandatory endpoints | `routes/api.php` | `AuthApiTest`, `ShipmentApiTest`, `TrackingApiTest`, `ExceptionApiTest` |
| JSON, 200/201, 401/403, 404, 422, pagination, server-side filter | controllers, resources, `bootstrap/app.php` | same |
| No stack traces in errors | `bootstrap/app.php` | `AuthApiTest::test_unknown_api_route_returns_json_404_without_stack_trace` |
| Postman collection | `docs/postman/` (35 requests with assertions, runnable with Newman) | run the collection |
| Unauthorized access tested automatically | feature tests | `ShipmentApiTest`, `ExceptionApiTest`, `TrackingApiTest`, `WebTest` |

## Stack and architecture (slides 14 and 15)

| Requirement | Implementation |
|-------------|----------------|
| Laravel 12, Blade, Tailwind, vanilla JS + Fetch, MySQL, Eloquent, Sanctum, HTTP Client, Leaflet, Chart.js | project stack (PHP 8.2+ is enough for Laravel 12) |
| Docker and CI (advanced) | `Dockerfile`, `docker-compose.yml`, `.github/workflows/ci.yml` |
| Services separate from controllers | `app/Services` (`ShipmentService`, `TrackingService`, `EtaService`, `ExceptionService`, ...), see [ARCHITECTURE.md](ARCHITECTURE.md) |

## Security and testing (slide 16)

| Requirement | Implementation | Test |
|-------------|----------------|------|
| Password hashing, auth middleware, backend authorization | `hashed` cast, `auth`/`auth:sanctum`, policies | `AuthApiTest`, `WebTest` |
| Validation, CSRF, XSS, SQL injection | form requests, `@csrf`, Blade escaping, Eloquent bindings | validation tests in every feature test |
| Mass-assignment protection | `$fillable` excludes `role`, `status`, delay fields | `AuthApiTest::test_register_always_creates_a_customer_even_if_role_is_sent`, `ShipmentApiTest::test_status_cannot_be_set_through_create` |
| `.env` and keys not in Git | `.gitignore`, `.env.example` | check `git status` before pushing |
| Auth, CRUD, tracking, delay tests; how to run tests in the README | `tests/`, README | `php artisan test` |

## Deliverables (slide 18) and definition of done (slide 24)

| Deliverable | Where |
|-------------|-------|
| Public GitHub repository with meaningful history | team task: commit in steps |
| Live deployment and demo account | team task, see [DEPLOYMENT.md](DEPLOYMENT.md) |
| English README, `.env.example`, seed data | `README.md`, `.env.example`, [DEMO_DATA.md](DEMO_DATA.md) |
| Screenshots (and optional demo video) | team task, list in `docs/screenshots/README.md` |
| ERD, data dictionary, architecture diagram | `docs/ERD.md`, `docs/DATA_DICTIONARY.md`, `docs/ARCHITECTURE.md` |
| Postman collection / API docs | `docs/postman/`, [API.md](API.md) |
| Test suite and evidence | `tests/`, [TEST_EVIDENCE.md](TEST_EVIDENCE.md) |
| Known limitations and future work | [KNOWN_LIMITATIONS.md](KNOWN_LIMITATIONS.md) |
| Attribution | [ATTRIBUTION.md](ATTRIBUTION.md) |
| Responsive UI | Tailwind responsive layout (sidebar collapses on small screens, tables scroll horizontally) |
