# Amusement Club Dashboard

A feature-rich web dashboard for the Amusement Club 3.0 Discord bot, built with Laravel and Livewire.

## Overview
Amusement Club is a Discord card trading bot using MongoDB as the primary datastore. The purpose of this dashboard is to provide a clean, visual interface for users to interact with their collections, track their progress, view trending cards, and manage their profile preferences outside of Discord.

## Tech Stack
- **Backend Framework**: Laravel 13
- **Frontend Framework**: Livewire 4 (Volt)
- **Database**: MongoDB (via `mongodb/laravel-mongodb`)
- **Authentication**: Discord OAuth2 (via Laravel Socialite)
- **Styling**: Vanilla CSS (Glassmorphism & Dark Mode theme)

---

## Prerequisites
Before you begin, ensure you have the following installed on your machine:
- **PHP** 8.3 or higher (Must include `xml` and `mongodb` extensions. E.g., `sudo apt-get install php8.4-xml php8.4-mongodb`)
- **Composer** (Dependency Manager for PHP)
- **Node.js** & **npm** (for building frontend assets)
- **MongoDB** (You need access to the existing Amusement Club `amuse3` database)
- A **Discord Developer Application** (for OAuth2 Login)

## Setup Instructions

### 1. Clone the Repository
```bash
git clone <repository-url>
cd amusement-dash
```

### 2. Install PHP Dependencies
Use Composer to install the backend dependencies, including Laravel, Livewire, and the MongoDB driver.
```bash
composer install
```
*Note: If you get a version mismatch error regarding `ext-mongodb`, you can resolve it by running `composer update mongodb/mongodb` to match your installed extension version, or bypass it with `composer install --ignore-platform-req=ext-mongodb`.*

### 3. Install NPM Dependencies
Install and build the frontend assets (CSS/JS) using Vite.
```bash
npm install
npm run build
```

### 4. Environment Configuration
Copy the sample `.env.example` file to create your own local `.env` configuration file.
```bash
cp .env.example .env
```
Next, generate your application encryption key:
```bash
php artisan key:generate
```

*Troubleshooting: If you see a `Cannot modify header information - headers already sent` error in your browser when loading the site, this usually means a deep exception occurred (often a missing `.env` configuration, no app key, or bad database connection). Check `storage/logs/laravel.log` for the true error.*

### 5. Configure MongoDB
Open your `.env` file and configure your database connection to point to the Amusement Club MongoDB instance.
```env
DB_CONNECTION=mongodb
DB_URI="mongodb://192.168.1.164:27017" # Replace with your MongoDB URI
DB_DATABASE=amuse3
```
*Note: We use `DB_URI` instead of standard SQL configurations because this project utilizes `mongodb/laravel-mongodb`.*

### 6. Configure Discord OAuth
To enable user sign-ins, you must provide your Discord application credentials in the `.env` file. 

1. Go to the [Discord Developer Portal](https://discord.com/developers/applications).
2. Create an Application and copy the Client ID and Client Secret.
3. In the "OAuth2" tab, add a redirect URI: `http://localhost:8000/auth/discord/callback`.
4. Update your `.env`:
```env
DISCORD_CLIENT_ID=your_client_id_here
DISCORD_CLIENT_SECRET=your_client_secret_here
DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback
DISCORD_BOT_TOKEN=your_bot_token_here   # used to look up avatars and guild names
```

### 7. Configure the Bot API
The dashboard reads MongoDB directly but sends every write through the bot's Express API. Point it at a running bot and use the same key as the bot's `webhooks.auth` setting in `config.yaml`:
```env
AMUSE_API_ROOT="http://localhost:9898"
AMUSE_API_KEY=same_value_as_webhooks_auth
AMUSE_CARD_ROOT="http://localhost:9898"   # where card images are served from
```

---

## Running the Application

To run the application locally, you need to start two servers: the PHP development server (for the backend) and the Vite development server (for hot-reloading frontend assets).

1. **Start the Laravel Backend Server:**
In your first terminal window, run:
```bash
php artisan serve
```
*This will start the server at `http://localhost:8000`.*

2. **Start the Vite Frontend Server:**
In a second terminal window, run:
```bash
npm run dev
```

You can now visit [http://localhost:8000](http://localhost:8000) in your browser to view and interact with the dashboard!

### Sharing Locally via Cloudflare Tunnel (For Beta Testers)
If you want to expose your local environment securely to external beta testers without deploying, you can use a free Cloudflare tunnel.

1. **Install cloudflared:**
On Debian/Ubuntu, download and install the package:
```bash
curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
sudo dpkg -i cloudflared.deb
```
2. **Start the tunnel:**
Make sure your Laravel server is running (`php artisan serve`). In a new terminal, run:
```bash
cloudflared tunnel --url http://127.0.0.1:8000
```
3. Cloudflare will output a public URL (e.g., `https://random-words.trycloudflare.com`). Share this link with your beta testers!
*Note: If assets fail to load over the tunnel, restart your server with `php artisan serve --host=0.0.0.0` or temporarily update your `.env` `APP_URL` to the Cloudflare link.*

---

## Running in Production

The steps below assume a single Linux server running **nginx + PHP-FPM**, with the bot's API and MongoDB reachable over a private network. The dashboard has no queue workers and no scheduled tasks, so nothing besides PHP-FPM needs to run.

### 1. Server requirements
- PHP 8.3+ with FPM and the `mongodb`, `xml`, `mbstring`, `curl` and `opcache` extensions
- Composer, and Node.js (only needed at build time)
- HTTPS in front of the site (Discord OAuth and secure cookies need it)
- Network access to MongoDB (`amuse3`), the bot API (`AMUSE_API_ROOT`) and `discord.com`

### 2. Get the code and build it
```bash
git clone <repository-url> /var/www/amusement-dash
cd /var/www/amusement-dash

composer install --no-dev --optimize-autoloader
npm install
npm run build          # outputs to public/build; node is not needed at runtime
```

### 3. Production `.env`
Start from `.env.example` and change at least these values. **Never deploy with `APP_DEBUG=true`**: the error page shows environment values, including API keys and tokens.
```env
APP_NAME="Amusement Club"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

LOG_LEVEL=warning

DB_CONNECTION=mongodb
DB_URI="mongodb://<host>:27017/amuse3"
DB_DATABASE=amuse3

# Keep the dashboard's own sessions and cache out of the shared amuse3 database.
SESSION_DRIVER=file
CACHE_STORE=file
SESSION_SECURE_COOKIE=true

DISCORD_CLIENT_ID=...
DISCORD_CLIENT_SECRET=...
DISCORD_REDIRECT_URI="${APP_URL}/auth/discord/callback"
DISCORD_BOT_TOKEN=...

AMUSE_API_ROOT="http://<bot-host>:<webhooks.port>"
AMUSE_API_KEY=...      # same as webhooks.auth in the bot's config.yaml
AMUSE_CARD_ROOT="https://c.amu.cards"
```
Then generate the app key once (keep it stable across deploys, or every session is invalidated):
```bash
php artisan key:generate
```
In the Discord Developer Portal, add the production callback URL (`https://your-domain.example/auth/discord/callback`) to the OAuth2 redirects.

> **Do not run `php artisan migrate`.** The database belongs to the bot. The bundled migrations are Laravel defaults and are not needed.

### 4. Permissions
PHP-FPM must be able to write to `storage/` and `bootstrap/cache/`:
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

### 5. Cache the framework
```bash
php artisan optimize   # caches config, routes, views and events
```
Once config is cached, `.env` is no longer read at runtime, so **re-run `php artisan optimize` after every `.env` change**. (Application code must read settings through `config()`, never `env()`, or the value is `null` here. API settings live under `services.amuse.*` in `config/services.php`.)

### 6. nginx
```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.example;
    root /var/www/amusement-dash/public;

    # ssl_certificate / ssl_certificate_key ...

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

server {
    listen 80;
    server_name your-domain.example;
    return 301 https://$host$request_uri;
}
```
The app trusts `X-Forwarded-*` headers from any proxy (`bootstrap/app.php`). That is fine behind nginx or Cloudflare, but the PHP-FPM socket and any internal port must not be reachable from the internet directly.

### 7. Check it
- `https://your-domain.example/up` returns 200 when Laravel boots.
- A red "API is currently unreachable" banner on every page means `AMUSE_API_ROOT` is wrong or the bot's API is down (it polls `<AMUSE_API_ROOT>/health`).
- Errors go to `storage/logs/laravel.log`.

### Deploying updates
```bash
cd /var/www/amusement-dash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan optimize
sudo systemctl reload php8.3-fpm     # clears OPcache so new code is picked up
php artisan up
```

### Turning features off before launch
Admins (users with the `admin` role in `amuse3.users`) can switch individual pages and claiming off from `/admin` without a deploy.

---

## Core Features
1. **Discord OAuth Login**: Users will sign in via Discord to access their personal data.
2. **Trending Dashboard**: The home page will showcase trending cards, highlighting new additions, or cards with high ratings and ownership percentages.
3. **Public Profile**: A web representation of the Discord `/profile` command. View basic info, card counts, achievements, and favorite cards.
4. **Card Collection View**: A highly visual grid representation of the user's current cards, equipped with powerful filters (similar to `/cards`).
5. **Active Auctions**: A dedicated page displaying currently running auctions in a Vickrey system.

## Design Aesthetics
The design aims to be exceptionally premium:
- **Dark Mode First**: Deep blacks and slate grays, accented with vibrant colors.
- **Glassmorphism**: Frosted glass effects on modals and floating cards.
- **Dynamic Feedback**: Hover effects on cards, smooth transitions between pages.
- **Clean Typography**: Modern, readable sans-serif fonts.
