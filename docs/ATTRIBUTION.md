# Attribution

## Frameworks and libraries

| Component | Use | Licence / source |
|-----------|-----|------------------|
| [Laravel 12](https://laravel.com) | Application framework, Eloquent, HTTP Client, testing | MIT |
| [Laravel Sanctum](https://laravel.com/docs/sanctum) | API tokens | MIT |
| [PHPUnit](https://phpunit.de), [Mockery](https://github.com/mockery/mockery), [Faker](https://fakerphp.org) | Testing and factories | BSD-3 / MIT |
| [Tailwind CSS 4](https://tailwindcss.com) | Styling | MIT |
| [Vite](https://vitejs.dev) + laravel-vite-plugin | Asset build | MIT |
| [Leaflet 1.9.4](https://leafletjs.com) | Interactive map (loaded from cdnjs) | BSD-2 |
| [Chart.js 4.4.1](https://www.chartjs.org) | Dashboard charts (loaded from cdnjs) | MIT |
| [Inter](https://rsms.me/inter/) via Google Fonts | Typeface | SIL OFL 1.1 |

## Data and services

| Service | Use | Terms |
|---------|-----|-------|
| © [OpenStreetMap](https://www.openstreetmap.org/copyright) contributors | Map tiles (attribution shown on every map) | ODbL / [tile usage policy](https://operations.osmfoundation.org/policies/tiles/) |
| [Nominatim](https://nominatim.org) | Geocoding of locations | [Usage policy](https://operations.osmfoundation.org/policies/nominatim/): identifying User-Agent, low request rate |
| [Open-Meteo](https://open-meteo.com) | Current weather (non-commercial use) | CC BY 4.0 |

## Project scaffolding

The project skeleton follows the structure of the official Laravel 12 application skeleton (`laravel/laravel`, MIT).
All application code under `app/`, `database/`, `resources/views/`, `routes/` and `tests/` was written for this project.
Demo data (carriers, customers, shipments, port coordinates) is fictional/synthetic.

## Team and responsibilities

> Fill in before submitting.

| Member | GitHub | Modules explained in the interview |
|--------|--------|------------------------------------|
| _Name_ | [AnandaElfira15](https://github.com/AnandaElfira15) | _e.g. tracking and delay rules_ |
| _Name_ | _username_ | _e.g. authorization and REST API_ |

## AI assistance disclosure

> **Edit this honestly, according to your lecturer's AI policy. Delete this note when done.**

An AI assistant (Claude, by Anthropic) was used to help generate the initial project scaffold, services, views, tests and
documentation. Automated tests were run locally by the team (`php artisan test`), bugs found during testing were fixed
(see the Git history, e.g. the route-name collision fix), and the team reviewed the code and is able to explain and modify it.
All third-party libraries are listed above.

## External code

No third-party repositories, templates or code snippets were copied other than the libraries listed above.
Add any additional source here (URL, author, licence, what was reused).
