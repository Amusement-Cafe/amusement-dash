<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Card extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'cards';

    // The primary identifier in the bot is usually cardID or the mongoid
    protected $fillable = [
        'cardID',
        'rarity',
        'animated',
        'canDrop',
        'collectionID',
        'cardName',
        'displayName',
        'cardURL',
        'eval',
        'ratingSum',
        'timesRated',
        'ownerCount',
        'meta',
        'stats'
    ];
}
