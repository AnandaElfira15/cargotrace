# Data dictionary

Types are MySQL types. "Enum" columns are stored as strings and validated by PHP backed enums in `app/Enums`.

## users
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | |
| email | varchar(255) UNIQUE | login |
| password | varchar(255) | bcrypt hash |
| role | varchar(20), indexed | `admin`, `operator`, `customer` (not mass assignable) |
| company | varchar(255) null | |
| is_active | boolean | deactivated users cannot log in; API tokens revoked |
| email_verified_at, remember_token, created_at, updated_at | | |

## carriers
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | |
| code | varchar(20) UNIQUE | e.g. `PML` |
| mode | varchar(20) | `sea`, `air`, `road`, `rail` |
| contact_email | varchar(255) null | |
| is_active | boolean | inactive carriers cannot be chosen for new shipments |

## locations
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | |
| code | varchar(20) UNIQUE | e.g. UN/LOCODE like `SGSIN` |
| type | varchar(20) | `port`, `airport`, `warehouse`, `city` |
| city | varchar(255) null | |
| country | varchar(100), indexed | |
| latitude / longitude | decimal(10,7) | validated −90..90 / −180..180 |

## shipments
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shipment_number | varchar(30) UNIQUE | auto-generated `CTyymmdd-NNNNN` when omitted |
| customer_id | FK users | owner, drives authorization |
| carrier_id | FK carriers | |
| origin_location_id / destination_location_id | FK locations | must differ |
| cargo_type | varchar(100) | |
| weight_kg / volume_cbm | decimal(12,2) | > 0 |
| expected_departure_at / expected_arrival_at | datetime | arrival must be after departure |
| estimated_arrival_at | datetime null | current ETA |
| actual_arrival_at | datetime null | set when DELIVERED |
| status | varchar(20), indexed | `CREATED`, `BOOKED`, `PICKED_UP`, `IN_TRANSIT`, `DELIVERED`, `CANCELLED` (system managed) |
| is_delayed | boolean, indexed | derived by EtaService |
| delay_hours | decimal(8,2) | derived |
| delay_severity | varchar(10) null | `LOW`, `MEDIUM`, `HIGH`, derived |
| created_by | FK users null | |
| created_at, updated_at, deleted_at | | soft delete |

## shipment_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shipment_id | FK shipments (cascade) | |
| description | varchar(255) | |
| quantity | unsigned int | ≥ 1 |
| weight_kg, volume_cbm | decimal(12,2) null | |
| hs_code | varchar(20) null | customs tariff code |

## tracking_events
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shipment_id | FK shipments (cascade) | |
| event_type | varchar(20) | `STATUS_UPDATE`, `LOCATION_UPDATE`, `NOTE`, `EXCEPTION` (system) |
| status | varchar(20) null | new status for STATUS_UPDATE events |
| occurred_at | datetime | not before the latest event, not in the future (5 min tolerance) |
| location_id | FK locations null | |
| latitude / longitude | decimal(10,7) null | copied from the location when omitted |
| description | text null | |
| is_exception | boolean | true for events created by exception reports |
| created_by | FK users null | |

Index: `(shipment_id, occurred_at)`. Rows are never updated.

## shipment_exceptions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shipment_id | FK shipments (cascade) | |
| type | varchar(30) | `CUSTOMS_HOLD`, `WEATHER_DELAY`, `PORT_CONGESTION`, `DAMAGED`, `MISROUTED`, `DOCUMENTATION_ISSUE` |
| severity | varchar(10) | `LOW`, `MEDIUM`, `HIGH` |
| status | varchar(20), indexed | `OPEN` → `INVESTIGATING` → `RESOLVED` (final) |
| description, resolution_notes | text null | |
| detected_at | datetime | |
| resolved_at | datetime null | never earlier than detected_at |
| reported_by / resolved_by | FK users null | |

## notifications
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | FK users (cascade) | recipient |
| shipment_id | FK shipments null | |
| type | varchar(40) | e.g. `shipment.delayed`, `exception.opened` |
| title, message | varchar / text | |
| read_at | timestamp null | |

## audit_logs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | FK users null | actor |
| action | varchar(60), indexed | e.g. `shipment.created`, `shipment.status_changed` |
| entity_type / entity_id | varchar(100) / bigint null | |
| old_values / new_values | json null | no passwords or tokens |
| ip_address | varchar(45) null | |
| created_at | timestamp | |

## settings
| Column | Type | Notes |
|--------|------|-------|
| key | varchar(100) PK | `delay.low_max_hours`, `delay.medium_max_hours` |
| value | text null | overrides `.env` defaults when present |

## personal_access_tokens
Standard Laravel Sanctum table.
