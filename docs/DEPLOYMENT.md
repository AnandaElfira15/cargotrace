# Deployment

## Option 1: Docker Compose (fastest)

```bash
docker compose up --build
# app: http://localhost:8000   (migrations + demo seed run automatically on start)
```

Environment variables are set in `docker-compose.yml`. Override secrets with a `.env` file next to it
(`DB_PASSWORD`, `DB_ROOT_PASSWORD`, `GROUP_SCENARIO`, `DEMO_PASSWORD`). Never commit real passwords.
`APP_KEY` is generated on first start inside the container (persist it via `APP_KEY` for production).

The image builds Tailwind with Node during `docker build`, installs production Composer dependencies and serves the app
with PHP's built-in server. For real traffic put it behind a reverse proxy (Nginx, Caddy, a PaaS router) that terminates TLS.

## Option 2: VPS with Nginx + PHP-FPM

1. Install PHP 8.3 (`fpm`, `mysql`, `mbstring`, `xml`, `bcmath`, `zip`), Nginx, MySQL 8, Composer, Node 20+.
2. Clone the repository and set up the app:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://your-domain, DB_*, GROUP_*, NOMINATIM_CONTACT_EMAIL
php artisan migrate --force --seed
npm install && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

3. Nginx server block (document root is `public/`):

```nginx
server {
    server_name your-domain;
    root /var/www/cargotrace/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```

4. HTTPS: `certbot --nginx -d your-domain`.
5. Scheduler (hourly delay re-evaluation): add `* * * * * cd /var/www/cargotrace && php artisan schedule:run >> /dev/null 2>&1` to the web user's crontab.


## Option 3: Render (Docker, free tier for demos)

Good for a public demo URL without managing a server. The repository contains `render.yaml` (blueprint) and a `Dockerfile`
that honours the `PORT` variable and supports PostgreSQL (`DB_CONNECTION=pgsql`) as well as MySQL.

1. Push the project to GitHub (already done).
2. Locally run `php artisan key:generate --show` and copy the `base64:...` value.
3. On render.com: **New + → Blueprint**, pick the `cargotrace` repository and apply.
4. When asked for the secrets, set `APP_KEY` (value from step 2) and `APP_URL` (the `https://<name>.onrender.com` URL).
5. Wait for the first deploy. The container runs migrations and the demo seeder on start (`docker/entrypoint.sh`).
6. Open the URL, log in with `admin@cargotrace.test` and the `DEMO_PASSWORD` you set, then put the URL in the README.

Notes: free web services may sleep after inactivity (first request is slow) and free databases may expire, so check Render's
current terms and re-seed if needed. Change `DEMO_PASSWORD` before sharing the URL publicly.

## Option 4: Railway (MySQL available)

1. New project → *Deploy from GitHub repo* → select `cargotrace` (it detects the `Dockerfile`).
2. Add a **MySQL** plugin/service to the same project.
3. In the app service variables set: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` (from `php artisan key:generate --show`),
   `APP_URL` (the generated domain), `DB_CONNECTION=mysql`, and map `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
   `DB_PASSWORD` to the MySQL service's variables. `SESSION_DRIVER=file`, `CACHE_STORE=file`.
4. Generate a public domain in *Settings → Networking* and open it.

## Option 5: Shared hosting / cPanel (typical campus or Indonesian hosting)

Requirements: PHP 8.2+, MySQL, SSH or a terminal in cPanel, Composer (or upload `vendor/` built locally).

1. Upload the project **outside** `public_html` (for example `~/cargotrace`).
2. Point the domain's document root to `~/cargotrace/public` (cPanel: *Domains → Document Root*). If that is impossible,
   copy the contents of `public/` into `public_html/` and edit its `index.php` so the two `require` paths point to `../cargotrace/...`.
3. Create a MySQL database and user in cPanel; copy `.env.example` to `.env` and fill `APP_URL`, `DB_*`, `APP_ENV=production`, `APP_DEBUG=false`.
4. In a terminal: `composer install --no-dev --optimize-autoloader`, `php artisan key:generate`, `php artisan migrate --force --seed`.
5. Upload the compiled CSS (`public/build`, built with `npm run build` locally) or rely on the CDN fallback.
6. Make `storage` and `bootstrap/cache` writable (`chmod -R 775`).
7. Add the scheduler as a cron job: `* * * * * php /home/USER/cargotrace/artisan schedule:run >> /dev/null 2>&1`.

## Optional: e-mail notifications

Set `NOTIFY_BY_EMAIL=true` and a real `MAIL_*` configuration (Mailtrap sandbox is ideal for demos). Customers receive e-mails
for new shipments, status changes, delays and exceptions; staff only for delays and new exceptions. A mail failure is logged
and never blocks the operation. With `MAIL_MAILER=log` the e-mails are written to `storage/logs/laravel.log` instead of being sent.

## Production checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, unique `APP_KEY`
- [ ] Strong database password, database user limited to the app schema
- [ ] `.env` is **not** in Git (it is listed in `.gitignore`)
- [ ] Demo password changed or demo accounts documented as public demo only
- [ ] `NOMINATIM_CONTACT_EMAIL` set (OpenStreetMap usage policy)
- [ ] HTTPS enabled
- [ ] Scheduler cron configured
- [ ] `php artisan test` is green in CI

## Demo data

`php artisan migrate:fresh --seed` rebuilds the demo data. By default it seeds all three group scenarios (`GROUP_SCENARIO=all`); use `a`, `b` or `c` for a single trade lane.
The seeder is idempotent for users, carriers and locations and skips shipments when shipments already exist.

## Live URL

Add the public URL and the demo account to the top of the README when the app is online.
