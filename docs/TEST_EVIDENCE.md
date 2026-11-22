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
| Role-based access on web routes | `tests/Feature/WebTest.php` | 403 for forbidden pages, pages render for each role |

## Latest run

> Paste the real output of `php artisan test` here after running it on your machine, plus the date and PHP version.

```
(paste output)
```

Manual checks performed before submission (tick when done):

- [ ] Migrations and seeder run on a clean MySQL database (`php artisan migrate:fresh --seed`)
- [ ] Login as admin, operator and customer; each sees only what the role allows
- [ ] Add tracking events through BOOKED → DELIVERED in the UI; try an invalid transition and see the error
- [ ] Change an ETA past the threshold and see the delay badge, rule text and notification
- [ ] Report and resolve an exception; check timeline and audit log
- [ ] Map shows origin, destination and tracking points; popups work
- [ ] Switch the network off (or use a wrong base URL) and confirm geocoding/weather show a friendly error
- [ ] Postman collection runs against the deployed URL
