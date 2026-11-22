# REST API reference

Base URL: `http://localhost:8000/api` · Content type: `application/json` · Header: `Accept: application/json`

Authenticated endpoints need `Authorization: Bearer <token>` (token from `POST /api/login`).
Import `docs/postman/CargoTrace.postman_collection.json` into Postman: logging in stores the token automatically.

## Conventions

| Topic | Rule |
|-------|------|
| Success | `200 OK`, `201 Created`, `204 No Content` (delete) |
| Unauthenticated | `401` `{"message": "Unauthenticated."}` |
| Forbidden | `403` `{"message": "This action is unauthorized."}` |
| Not found | `404` `{"message": "Resource not found."}` |
| Validation | `422` `{"message": "...", "errors": {"field": ["..."]}}` |
| Throttled | `429` (login/register 10 per minute per IP, API 120 per minute per user) |
| Server error | `500` `{"message": "Server error. Please try again later."}` (no stack trace) |
| Pagination | Laravel paginator: `data`, `links`, `meta` (`current_page`, `per_page`, `total`, ...) |
| Dates | ISO 8601 in responses; any parseable date accepted in requests |

## Authentication

| Method | Path | Body | Result |
|--------|------|------|--------|
| POST | `/register` | `name, email, password, password_confirmation, company?` | `201` + token. Role is always `customer`. |
| POST | `/login` | `email, password` | `200` + `token`, `user`; `401` bad credentials; `403` inactive |
| POST | `/logout` | | revokes the current token |
| GET | `/user` | | current user |

## Shipments

| Method | Path | Who | Notes |
|--------|------|-----|-------|
| GET | `/shipments` | all roles | customers only receive their own |
| POST | `/shipments` | admin, operator | status cannot be supplied; starts as `CREATED` with a first tracking event |
| GET | `/shipments/{id}` | owner, staff | |
| PUT / PATCH | `/shipments/{id}` | admin; operator while not terminal | re-evaluates delay |
| DELETE | `/shipments/{id}` | admin | soft delete |

List query parameters: `q` (shipment number), `status`, `carrier_id`, `origin_id`, `destination_id`, `date_from`, `date_to`
(on expected departure), `delayed` (`1`/`0`), `sort` (`shipment_number|status|expected_departure_at|expected_arrival_at|delay_hours|created_at`),
`direction` (`asc|desc`), `per_page` (1-50), `page`.

Create body:

```json
{
  "customer_id": 3,
  "carrier_id": 1,
  "origin_location_id": 1,
  "destination_location_id": 8,
  "cargo_type": "Electronics",
  "weight_kg": 1500.5,
  "volume_cbm": 12.25,
  "expected_departure_at": "2026-12-01T08:00:00+07:00",
  "expected_arrival_at": "2026-12-25T08:00:00+07:00",
  "estimated_arrival_at": null,
  "items": [{ "description": "Laptops", "quantity": 10, "weight_kg": 300, "hs_code": "8471.30" }]
}
```

Response (shortened):

```json
{ "data": { "id": 12, "shipment_number": "CT261121-04821", "status": "CREATED",
            "delay": { "is_delayed": false, "delay_hours": 0, "severity": null }, "...": "..." } }
```

## Tracking

| Method | Path | Who |
|--------|------|-----|
| GET | `/shipments/{id}/tracking` | owner, staff: chronological events + `meta.current_location` |
| POST | `/shipments/{id}/tracking` | admin, operator |

```json
{
  "event_type": "STATUS_UPDATE",          // STATUS_UPDATE | LOCATION_UPDATE | NOTE
  "status": "IN_TRANSIT",                 // required for STATUS_UPDATE
  "occurred_at": "2026-11-20T10:00:00+07:00",
  "location_id": 5,                       // optional; coordinates default to the location
  "latitude": 6.9497, "longitude": 79.8428, // optional, must be given together
  "estimated_arrival_at": "2026-12-26T20:00:00+07:00", // optional new ETA
  "description": "Departed Colombo"
}
```

Rules: valid transition only (`CREATED→BOOKED→PICKED_UP→IN_TRANSIT→DELIVERED`, `CREATED/BOOKED→CANCELLED`),
`occurred_at` not earlier than the latest event and not in the future, terminal shipments accept no events.
Violations return `422`.

## Exceptions

| Method | Path | Who |
|--------|------|-----|
| GET | `/shipments/{id}/exceptions` | owner, staff |
| POST | `/shipments/{id}/exceptions` | admin, operator: `type`, `severity`, `description?`, `detected_at?` |
| PATCH | `/exceptions/{id}` | admin, operator: `status` (`OPEN→INVESTIGATING→RESOLVED`), `severity`, `description`, `resolution_notes`, `resolved_at` |

## Reference data

| Method | Path | Who |
|--------|------|-----|
| GET | `/carriers`, `/locations` | any authenticated user (`q`, `per_page`) |
| POST | `/carriers` | admin: `name, code, mode, contact_email?, is_active?` |
| POST | `/locations` | admin: `name, code, type, city?, country, latitude, longitude` |

## Quick curl session

```bash
TOKEN=$(curl -s -X POST localhost:8000/api/login -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"operator@cargotrace.test","password":"password"}' | jq -r .token)

curl -s localhost:8000/api/shipments?status=IN_TRANSIT -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```
