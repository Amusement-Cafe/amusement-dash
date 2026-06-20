<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserWishlist extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'userwishlists';

    protected $fillable = [
        'userID',
        'cardID',
        'added',
    ];
}
