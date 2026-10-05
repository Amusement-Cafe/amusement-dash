# Agent Rules for Amusement Club Dashboard

This project is a web dashboard for the "Amusement Club 3.0" Discord bot. It is built using **Laravel** and **Livewire** to simplify user card displays, trading, and account management.

## General Guidelines
1. **Stack**: Use PHP 8.3+, Laravel 13, Livewire 4 (Volt), and Vanilla CSS.
2. **Database**: The application connects to the **MongoDB** database (`amuse3`) used by the Discord bot. Use the `mongodb/laravel-mongodb` package. Create Eloquent models that extend `MongoDB\Laravel\Eloquent\Model`.
3. **Database Schema Reference**: You can instantly view the entire MongoDB database schema by reading `.agents/amuse3Schema.js`! Use this file as your single source of truth for database collections without needing to run queries or check the bot's raw source code.
4. **Mongoose Collection Naming Gotcha**: Mongoose automatically lowercases and pluralizes collection names. For multi-word collections like `userCards` or `userWishlists`, Mongoose creates `usercards` and `userwishlists` in MongoDB. You **must** explicitly set `protected $table = 'usercards';` in your Laravel models entirely in lowercase, or Laravel will query an empty phantom collection.
5. **Discord ID Precision Gotcha**: Discord User IDs are 18+ digit snowflakes that exceed Javascript's `MAX_SAFE_INTEGER`. Whenever accepting a user ID via Livewire (e.g., `#[Url]`), you **must strictly type-hint it as a string** (`public string $owner = '';`). Otherwise, Livewire's Javascript engine will silently round the ID and mangle it when synchronizing the URL state.
6. **Promo Cards**: Promo cards do not natively use `rarity: 6` in the `cards` table. They are identified by checking if their parent collection has `promo: true`.
7. **Design Aesthetic**: Adhere strictly to the `web_application_development` system guidelines. Use a rich, modern design with smooth gradients, dark mode by default, glassmorphism, rainbow diffused animations, and micro-animations. Avoid basic generic styling.
8. **No Placeholders**: Do not leave components half-finished.
9. **Settings via `config()`, never `env()`**: Outside `config/`, read settings with `config('services.amuse.api_root')`, `config('services.amuse.api_key')`, `config('services.amuse.card_root')` and `config('services.discord.bot_token')`. Production caches config, after which `env()` returns null and every bot API call breaks.
10. **Values inside JS attributes use `Js::from()`**: In `wire:click`, `@click`, `x-data`, `onclick` and similar, write `wire:click="removeTag({{ Js::from($tag) }})"`, never `'{{ $tag }}'`. Blade's escaping is decoded by the browser before the expression runs, so a quoted echo of user input (e.g. a `#[Url]` property) is an XSS hole.
11. **Show bot API failures with `ApiError::message($response, 'Fallback.')`** (`app/Support/ApiError.php`), never `$response->body()`. It logs the failure, shows the raw status and body when `APP_DEBUG` is on, and in production passes through only 4xx messages the bot wrote for users. On the bot side, user-facing errors are plain sentences; developer errors start with `Bad Request -` or `Forbidden -`.

## Bot Database Schema Notes
- **Users**: `users` collection. Key fields: `userID`, `username`, `tomatoes`, `vials`, `lemons`, `xp`.
- **Cards**: `cards` collection. 
- **Collections**: `collections` collection.
- **User Cards**: `usercards` collection.
- **Auctions**: `auctions` collection.
- **Wishlists**: `userwishlists` collection.
- Refer to `.agents/amuse3Schema.js` for full details.

## Key Features Implementation Status
- **Authentication**: ✅ Sign-in via Discord OAuth.
- **Card Collection View**: ✅ Standalone directory with rarity/collection filters, promo toggles, and dynamic URL state targeting specific user collections.
- **Global Profile**: ✅ Dynamic glassmorphism profile showing level-calculated avatars, balances, stats, and wishlisted cards.
- **Auctions**: ✅ Real-time Vickrey auctions viewer with dynamic seller profiles.
- **Trending Cards**: ⏳ Main page showcasing trending cards (Pending).
- **Personal Dashboard / Inventory**: ⏳ Showing inventory, plots, and quest lists (Pending).
- **Preferences**: ⏳ UI to manage user preferences (Pending).

9. **Currency Emojis**: Always keep tomatoes, vials, and lemons displayed as their default text emojis (`🍅`, `🧪`, `🍋`). Do not replace these specific currencies with icon libraries (like Phosphor or FontAwesome).
10. **Livewire Component Generation**: Running `php artisan make:livewire` often generates a dummy boilerplate file with an invalid/unexpected name (e.g., inside `components/` prefixed with a lightning bolt `⚡`). To avoid bugs, **do not** use `make:livewire`. Instead, manually create new `.blade.php` files directly inside `resources/views/livewire/` with the standard Volt `<?php ... new class extends Component ... ?>` syntax.
11. **MongoDB Random Order Gotcha**: The standard Eloquent `->inRandomOrder()` method translates to a SQL `ORDER BY RAND()` query, which throws a `BadMethodCallException` since it is not natively supported by the `mongodb/laravel-mongodb` driver. To randomize results, you must retrieve the records first (e.g., `->get()`, `->all()`) and then use Laravel's Collection randomization methods like `->shuffle()`, `->random()`, or `->take()`.
12. **API Interface for Database Writes**: ALWAYS use the API interface described in `API-README.md` to mutate data instead of writing directly to the database. The MongoDB database should be treated as read-only for Laravel.
