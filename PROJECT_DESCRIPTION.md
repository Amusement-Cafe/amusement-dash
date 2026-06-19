# Amusement Club Dashboard

A feature-rich web dashboard for the Amusement Club 3.0 Discord bot, built with Laravel and Livewire.

## Overview
Amusement Club is a Discord card trading bot using MongoDB as the primary datastore. The purpose of this dashboard is to provide a clean, visual interface for users to interact with their collections, track their progress, view trending cards, and manage their profile preferences outside of Discord.

## Tech Stack
- **Backend Framework**: Laravel
- **Frontend Framework**: Livewire
- **Database**: MongoDB (connecting to the bot's existing database)
- **Authentication**: Discord OAuth2

## Core Features
1. **Discord OAuth Login**: Users will sign in via Discord to access their personal data.
2. **Trending Dashboard**: The home page will showcase trending cards, highlighting new additions, or cards with high ratings and ownership percentages (randomized).
3. **Public Profile**: A web representation of the Discord `/profile` command. It displays:
   - Basic info
   - Card and star counts
   - Achievements
   - Favorite cards
4. **Card Collection View**: A highly visual grid/list representation of the user's current cards, equipped with powerful filters (similar to `/cards`).
5. **Active Auctions**: A dedicated page displaying currently running auctions (syncs with `/auctions`).
6. **Personal Management**: An extended profile page allowing users to view:
   - Inventory (`/inventory`)
   - Plots (`/plots`)
   - Quests (`/quest list`)
7. **Preferences**: A settings page mimicking `/preferences` to toggle notifications and interaction flags.

## Design
The design must be exceptionally premium:
- **Dark Mode First**: Deep blacks and slate grays, accented with vibrant colors.
- **Glassmorphism**: Frosted glass effects on modals and floating cards.
- **Animations**: Hover effects on cards, smooth transitions between pages.
- **Typography**: Modern, readable sans-serif fonts (e.g., Inter, Outfit).
