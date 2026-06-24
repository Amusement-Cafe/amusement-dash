<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Leaderboard extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'leaderboards';

    protected $fillable = [
        'type',
        'data',
        'expires_at'
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
