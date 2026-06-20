<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Auction extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'auctions';

    protected $fillable = [
        'auctionID',
        'guildID',
        'userID',
        'lastBidderID',
        'ended',
        'cancelled',
        'price',
        'highBid',
        'cardID',
        'bids',
        'expires',
        'time',
    ];
}
