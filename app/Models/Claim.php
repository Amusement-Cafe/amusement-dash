<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Claim extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'claims';

    protected $fillable = [
        'claimID',
        'userID',
        'guildID',
        'lockCol',
        'cost',
        'cardIDs',
        'promo',
        'timeClaimed',
    ];
}
