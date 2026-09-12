# House of Ramen

House of Ramen's website — a public menu/gallery/about/contact site for the restaurant, plus a simplified admin panel for managing the menu and restaurant content. It's one Laravel project with two parts: a public Vue/Inertia site for customers, and a server-rendered admin panel (Dashboard, Users, Roles, Menus, Logs, and Restaurant content management).

## Tech stack

- **Backend:** PHP 8.4, Laravel 13, MySQL
- **Public site:** Vue 3 + Inertia.js, Tailwind CSS 4, Vite (SSR-capable)
- **Admin panel:** server-rendered Blade views, Bootstrap/jQuery admin theme
- **Testing:** Pest

## Requirements

- PHP 8.4 (with the `gd`, `pdo_mysql`, `mbstring`, `intl`, `xml`, `zip`, `curl` extensions — standard with most installs)
- Composer 2.x
- Node.js + npm (a recent LTS; developed against Node 22)
- MySQL 8.x
- Redis is **not** required — cache, session, and queue all default to the `database` driver.

## Local setup

```bash
# 1. Clone the project
git clone <repo-url>
cd house-of-ramen

# 2. Install PHP dependencies
composer install

# 3. Configure the environment
cp .env.example .env
```

Edit `.env`:

- **`APP_URL`** — if serving locally with `php artisan serve` (i.e. `composer dev`), this must include the port (e.g. `http://localhost:8000`) — image URLs are generated from this value.
- **`DB_*`** — point at a MySQL database you've created (defaults assume a `house_of_ramen` database with user `root` / password `root` on `127.0.0.1:3306`).

```bash
# 4. Generate the app key
php artisan key:generate

# 5. Configure the database
#    Create the MySQL database named in DB_DATABASE above, if it doesn't exist yet.

# 6. Run migrations and seed (admin user, admin nav menus, and the
#    House of Ramen restaurant + menu content, entirely offline)
php artisan migrate
php artisan db:seed --force

# 7. Install frontend dependencies and build assets (client + SSR bundles)
php artisan storage:link
npm install
npm run build:ssr

# 8. Start the application (server, queue listener, log tailer, and Vite together)
composer dev
```

`composer setup` runs steps 2–7 above in one command, if you'd rather not run them individually.

## Default admin login

Step 6 (`db:seed`) creates an admin account if one doesn't already exist. Log in at `/login` with:

- **Username:** `admin`
- **Password:** `password`

This is a fixed, well-known credential by design for local development — change it immediately on any environment reachable outside a trusted network.

## Environment variables

- **`APP_URL`** — must include the port for local `php artisan serve`/`composer dev` (see above).
- **`DB_*`** — your local MySQL connection.
- **`ADMIN_SEED_EMAIL`** *(optional)* — sets the seeded admin's email address instead of the default.
