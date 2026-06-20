<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'mongodb';
    protected $table = 'users';

    // In Amusement Club, the Discord user ID is stored as "userID"
    protected $fillable = [
        'userID',
        'username',
        'tomatoes',
        'vials',
        'lemons',
        'xp',
        'preferences',
        'roles',
        'achievements',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            //
        ];
    }
}
