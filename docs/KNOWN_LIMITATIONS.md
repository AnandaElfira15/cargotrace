# Known limitations and future work

## Limitations

* **Synthetic data.** Carriers, customers, shipments and events are fictional; port coordinates are approximate.
* **No real integrations.** No live carrier, port or customs feeds, no payments, no GPS devices (explicitly out of scope).
* **One carrier and one route per shipment.** Multi-leg journeys, transhipment planning and container-level tracking are not modelled. Waypoints appear only as tracking events.
* **Strict event ordering.** An event cannot be earlier than the latest recorded one. Late-arriving back-dated information must be entered as a note with the current time.
* **Rule-based delays only.** Delay detection uses deterministic rules; weather is informational and is not used to predict delays.
* **Time zone.** Dates are stored and displayed in the application time zone (`APP_TIMEZONE`, default `Asia/Jakarta`); there is no per-user time zone.
* **In-app notifications only.** No email, push or SMS delivery. No notification preferences.
* **Authentication scope.** No password reset, email verification or two-factor authentication. API tokens do not expire until logout or deactivation.
* **External services.** Nominatim and Open-Meteo are public services with fair-use limits; the app handles failures gracefully but does not queue or retry requests. Weather results are cached for 10 minutes.
* **Map tiles.** The standard OpenStreetMap tile server is intended for light use; a production deployment should use a tile provider that fits its traffic.
* **Delay re-evaluation.** Shipments that become late only because time passed are flagged by the hourly `cargotrace:evaluate-delays` command, so the scheduler must be running in production.
* **Asset pipeline.** Without `npm run build` the UI falls back to the Tailwind browser build from a CDN (fine for demos, not for production).
* **Audit log.** Covers shipments, tracking, exceptions, users, carriers, locations and settings. It is not tamper-proof.

## Future work

* Email notifications via Laravel Mail and queues; per-user notification preferences.
* Multi-leg shipments (segments, transhipment ports) and route-aware ETA recalculation.
* Carrier webhooks or EDI/API ingestion of tracking events.
* Customer-facing public tracking link (read-only, tokenised).
* CSV/PDF export of shipments and KPI reports.
* Rate limiting per API token, token abilities/scopes and expiry.
* Browser-level (Dusk) tests for the dashboard and map.
* Localisation (Indonesian/English UI).
