<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Dashboard-only per-user data, one record per Discord user, keyed by userID.
 * The bot never reads this collection, and the dashboard never stores its own
 * state on the bot's `users` documents.
 */
class DashboardUser extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'dashboard_users';

    protected $fillable = [
        'userID',
        'remember_token',
    ];

    protected $hidden = [
        'remember_token',
    ];

    /**
     * Set fields on a user's record, creating it if needed. A single upsert,
     * so two logins at once can't create duplicate records.
     */
    public static function put(string $userID, array $values): void
    {
        self::upsert([['userID' => $userID] + $values], ['userID'], array_keys($values));
    }
}
