# Amusement Club Dashboard

A feature-rich web dashboard for the Amusement Club 3.0 Discord bot, built with Laravel and Livewire.

## Overview
Amusement Club is a Discord card trading bot using MongoDB as the primary datastore. The purpose of this dashboard is to provide a clean, visual interface for users to interact with their collections, track their progress, view trending cards, and manage their profile preferences outside of Discord.

## Tech Stack
- **Backend Framework**: Laravel 11.x
- **Frontend Framework**: Livewire 3 (Volt)
- **Database**: MongoDB (via `mongodb/laravel-mongodb`)
- **Authentication**: Discord OAuth2 (via Laravel Socialite)
- **Styling**: Vanilla CSS (Glassmorphism & Dark Mode theme)

---

## Prerequisites
Before you begin, ensure you have the following installed on your machine:
- **PHP** 8.2 or higher (Must include `xml` and `mongodb` extensions. E.g., `sudo apt-get install php8.4-xml php8.4-mongodb`)
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
