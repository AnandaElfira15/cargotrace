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

`php artisan migrate:fresh --seed` rebuilds the demo data. Choose the group data set with `GROUP_SCENARIO=a|b|c`.
The seeder is idempotent for users, carriers and locations and skips shipments when shipments already exist.

## Live URL

Add the public URL and the demo account to the top of the README when the app is online.
