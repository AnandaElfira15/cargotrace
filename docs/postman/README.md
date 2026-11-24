# Postman collection

## Postman app

1. Start the app (`php artisan serve`) on a freshly seeded database: `php artisan migrate:fresh --seed`.
2. Postman → **Import** → select `CargoTrace.postman_collection.json`.
3. Check the collection variables: `baseUrl` (default `http://127.0.0.1:8000`), `password` (your `DEMO_PASSWORD`, default `password`),
   `customerId` (id of `customer@cargotrace.test`, `3` with the default seeder), `laneId` (id of trade lane A, `1` with the default seeder).
4. Click **Run collection** (Collection Runner). Every request has an assertion (status code and, where useful, response content).

The folders run in order: authentication, reference data, shipments, tracking, exceptions, authorization checks, cleanup.
Tokens are stored automatically after each login. Dates are generated in a collection pre-request script, so the run works on any day.

## Command line (Newman)

```bash
npx newman run docs/postman/CargoTrace.postman_collection.json
# with another URL or password:
npx newman run docs/postman/CargoTrace.postman_collection.json --env-var "baseUrl=https://your-app.example" --env-var "password=your-demo-password"
```

All assertions should pass. Login and register are throttled to 10 requests per minute per IP, so wait a minute between repeated runs.
