<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BotCollection extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'collections';

    protected $fillable = [
        'collectionID',
        'name',
        'origin',
        'creatorID',
        'stars',
        'aliases',
        'promo',
        'compressed',
        'inClaimPool',
        'rarity',
        'dateAdded',
    ];
}
