<?php
namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;

class UserStat extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'userstats';
}
