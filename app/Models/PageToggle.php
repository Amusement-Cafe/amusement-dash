<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Dashboard-local switch for pages and features that can be turned off from the
 * admin panel. Anything with no stored record is enabled.
 */
class PageToggle extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'page_toggles';

    protected $fillable = [
        'page',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    // Pages an admin can turn off, keyed by the name used in the `page:` middleware.
    public const PAGES = [
        'auctions' => 'Auctions',
        'store' => 'Store',
        'leaderboards' => 'Leaderboards',
        'heroes' => 'Heroes',
        'plots' => 'Plots',
    ];

    // Dashboard features that live inside a page that stays reachable.
    public const FEATURES = [
        'claiming' => 'Claiming',
        'ticket_redeem' => 'Ticket Redeeming',
    ];

    public static function toggles(): array
    {
        return self::PAGES + self::FEATURES;
    }

    private static ?array $states = null;

    public static function isEnabled(string $page): bool
    {
        self::$states ??= self::whereIn('page', array_keys(self::toggles()))
            ->get()
            ->pluck('enabled', 'page')
            ->all();

        return self::$states[$page] ?? true;
    }

    public static function setEnabled(string $page, bool $enabled): void
    {
        abort_unless(array_key_exists($page, self::toggles()), 404);

        self::updateOrCreate(['page' => $page], ['enabled' => $enabled]);
        self::$states = null;
    }
}
