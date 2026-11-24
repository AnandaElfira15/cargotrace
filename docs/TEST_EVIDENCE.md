# Test evidence

## How to run

```bash
php artisan test                 # whole suite (SQLite in memory, no MySQL needed)
php artisan test --filter=EtaDelayTest
composer test
```

CI runs the same command on every push (`.github/workflows/ci.yml`).

## Requirement to test mapping

| Brief requirement | Test file | What is verified |
|-------------------|-----------|------------------|
| Authentication: valid / invalid login, access without login | `tests/Feature/AuthApiTest.php`, `tests/Feature/WebTest.php` | token login, 401 on bad credentials, 403 inactive, 401 on protected API routes, redirects to login, register cannot set `role` |
| Shipment CRUD + validation + authorization | `tests/Feature/ShipmentApiTest.php` | create/update/delete, 422 rules (dates, same origin/destination, required fields), customer scope (own shipments only), operator vs admin permissions, status cannot be mass assigned, filters, sorting, pagination |
| Tracking events, invalid status transition | `tests/Feature/TrackingApiTest.php`, `tests/Unit/StatusRulesTest.php` | full lifecycle, invalid transitions rejected with nothing stored, terminal shipments, chronological and future-time validation, append-only history, current location, customer cannot add events |
| ETA / delay rule | `tests/Feature/EtaDelayTest.php` | boundaries 0/6/6.5/24/24.5/72 h, configurable threshold, explanation text, past-ETA rule, delivered shipments, notification once, hourly command |
| Exceptions | `tests/Feature/ExceptionApiTest.php` | create, timeline event, validation, status flow, resolved is final, customer restrictions |
| External API handling | `tests/Feature/ExternalApiTest.php` | success, empty result, timeout, HTTP 500, unusable payload, authorization |
| Dashboard from real data | `tests/Feature/DashboardTest.php` | totals, KPIs, scoping per customer, empty database |
| Optional e-mail notifications | `tests/Feature/EmailNotificationTest.php` | disabled by default, customer vs staff recipients, failure does not break the workflow, e-mail content |
| Role-based access on web routes | `tests/Feature/WebTest.php` | 403 for forbidden pages, pages render for each role |
| Web form actions (tracking, exceptions, shipments, carriers, locations, users, settings, audit, notifications) | `tests/Feature/WebActionsTest.php` | valid and invalid submissions, permissions, in-use deletion guards, token revocation, threshold changes |

## Latest run

Generate the evidence file with one command (PowerShell or any shell), then commit it:

```powershell
php artisan test > docs/test-output.txt 2>&1
```

Environment of the development runs: PHP 8.2, Laravel 12.x, SQLite in memory. The first full run found two issues
(a wrong float assertion in a test and a real route-name collision between web and API routes); both were fixed and
committed (`fix: rename API shipment routes so web links no longer point to /api`).

Final result (update after your last run): `Tests: ___ passed (___ assertions)` on `____-__-__`.

Manual checks performed before submission (tick when done):

- [ ] Migrations and seeder run on a clean MySQL database (`php artisan migrate:fresh --seed`)
- [ ] Login as admin, operator and customer; each sees only what the role allows
- [ ] Add tracking events through BOOKED → DELIVERED in the UI; try an invalid transition and see the error
- [ ] Change an ETA past the threshold and see the delay badge, rule text and notification
- [ ] Report and resolve an exception; check timeline and audit log
- [ ] Map shows origin, destination and tracking points; popups work
- [ ] Switch the network off (or use a wrong base URL) and confirm geocoding/weather show a friendly error
- [ ] Postman collection runs against the deployed URL
