<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Card extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'cards';

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

    protected $appends = ['cardURL'];

    public function getCardURLAttribute()
    {
        $val = $this->attributes['cardURL'] ?? null;
        if ($val && str_starts_with($val, 'https://c.amu.cards')) {
            $cardRoot = config('services.amuse.card_root');
            return str_replace('https://c.amu.cards', $cardRoot, $val);
        }
        return $val;
    }
}
