# Agent Rules for Amusement Club Dashboard

This project is a web dashboard for the "Amusement Club 3.0" Discord bot. It is built using **Laravel** and **Livewire** to simplify user card displays, trading, and account management.

## General Guidelines
1. **Stack**: Use PHP 8.5+, Laravel 11.x+, Livewire 4.x+, and Vanilla CSS (or Tailwind if specified).
2. **Database**: The application will connect to the existing **MongoDB** database used by the Discord bot. You should configure `mongodb/laravel-mongodb` package when setting up database connections. Create Eloquent models that extend `MongoDB\Laravel\Eloquent\Model`.
3. **Bot Codebase**: The Discord bot code is located at `../amusementclub3.0/bots/amusement`. The database models are located at `../amusementclub3.0/db`. Reference these files when you need to understand schemas and default values.
4. **Design Aesthetic**: Adhere strictly to the `web_application_development` system guidelines. Use a rich, modern design with smooth gradients, dark mode by default, glassmorphism, and micro-animations. Avoid basic generic styling.
5. **No Placeholders**: Do not leave components half-finished. If an image is needed, generate one using the `generate_image` tool.

## Bot Database Schema Notes
- **Users**: `users` collection. Key fields: `userID`, `username`, `tomatoes`, `vials`, `lemons`, `xp`.
- **Cards**: `cards` collection.
- **Other Collections**: `auctions`, `userInventories`, `plots`, `userQuests`.

## Key Features to Implement
- **Authentication**: Sign-in via Discord OAuth.
- **Trending Cards**: Main page showing new or high-rating cards.
- **Global Profile**: Everyone can view a user's stats, achievements, and favorite cards.
- **My Cards**: Display user's cards with various filters.
- **Auctions**: Display currently running auctions.
- **Personal Dashboard**: Showing inventory, plots, and quest lists.
- **Preferences**: UI to manage user preferences.
