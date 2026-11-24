# Demo data

All demo data is **synthetic but consistent**: fictional carriers and customers, approximate (not surveyed) port coordinates,
and timelines generated through the real services (`ShipmentService`, `TrackingService`, `ExceptionService`), so status rules,
delay flags, notifications and the audit log look exactly like production data.

Rebuild it at any time with `php artisan migrate:fresh --seed`.

## Accounts

| Email | Role | Notes |
|-------|------|-------|
| `admin@cargotrace.test` | Administrator | user id 1 |
| `operator@cargotrace.test` | Logistics Operator | user id 2 |
| `customer@cargotrace.test` | Customer | user id 3, owns every third shipment |
| `lars@eurotrade.test` | Customer | user id 4 |
| `hana@hanseatic.test` | Customer | user id 5 |

Password: value of `DEMO_PASSWORD` in `.env` (default `password`).

## Trade lanes: Groups A, B and C together

One database holds **all three group scenarios** (`GROUP_SCENARIO=all`, the default). Each lane has its own route family,
carriers, delay thresholds, main exception and highlighted KPI:

| Lane | Route family | LOW up to / MEDIUM up to | Main exception | KPI |
|------|--------------|--------------------------|----------------|-----|
| **A** | Southeast Asia → Europe (Singapore, Port Klang, Jakarta, Laem Chabang → Rotterdam, Hamburg, Antwerp via Colombo, Jeddah, Port Said) | 6 h / 12 h | CUSTOMS_HOLD | on_time_delivery_rate |
| **B** | Asia → North America (Shanghai, Shenzhen, Busan → Los Angeles, Long Beach, Vancouver, Tacoma via Yokohama, Honolulu) | 9 h / 18 h | WEATHER_DELAY | average_delay_duration |
| **C** | Southeast Asia → Australia (Singapore, Port Klang, Jakarta, Laem Chabang → Brisbane, Fremantle, Melbourne, Sydney via Surabaya, Darwin) | 12 h / 24 h | DOCUMENTATION_ISSUE | exception_resolution_time |

Scenario files: `database/seeders/scenarios/{a,b,c}.php` (lane parameters, carriers, locations, routes, cargo types).
Seed a single lane with `GROUP_SCENARIO=a|b|c`. Rebuild everything with `php artisan migrate:fresh --seed`.

## What the seeder creates: 3 lanes × 22 shipments = 66 shipments

The same lifecycle pattern is generated for each lane:

| Group | Count per lane | Purpose in the demo |
|-------|----------------|---------------------|
| DELIVERED | 7 | 4 on time, 1 delivered 4 h late, 1 delivered 15 h late and 1 delivered 30 h late (the last two had a resolved exception). Feeds the on-time delivery rate KPI. |
| IN_TRANSIT | 8 | 3 on schedule, 4 with an ETA later than expected (5, 9, 20 and 40 h) and 1 overdue shipment that is past its expected arrival without an ETA (the clock rule). |
| PICKED_UP | 2 | Early lifecycle. |
| BOOKED | 2 | Early lifecycle. |
| CREATED | 2 | Only the initial event: use these to demonstrate adding events. |
| CANCELLED | 1 | Terminal status. |

Exceptions (5 per lane, 15 in total): 2 RESOLVED, 1 INVESTIGATING (weather delay) and 2 OPEN (the lane's main exception and a documentation issue).

The **same delay in hours gets a different severity per lane**: a 15 h delay is HIGH in lane A (limit 12 h) but MEDIUM in lanes B and C
(limits 18 h and 24 h). That makes the per-group thresholds easy to demonstrate. Change the thresholds on the Settings page and run
`php artisan cargotrace:evaluate-delays` to see the labels change.

Every shipment has a first tracking event at its origin, status events along the lifecycle, and waypoint location events so the map
shows a route. The seeder also generates audit logs and in-app notifications for these actions (the admin sees many notifications
because staff are notified about every event). E-mails are never sent while seeding.

## Realism and limits

* Transit times are 9-28 days depending on the route; timestamps are relative to "now", so a fresh seed always has in-flight shipments.
* Coordinates are approximate port positions and cargo/customers are fictional (see [KNOWN_LIMITATIONS.md](KNOWN_LIMITATIONS.md)).
