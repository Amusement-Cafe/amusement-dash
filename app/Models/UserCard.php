<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserCard extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'usercards';

    protected $fillable = [
        'userID',
        'cardID',
        'amount',
        'rating',
        'fav',
        'locked',
        'acquired',
    ];
}
