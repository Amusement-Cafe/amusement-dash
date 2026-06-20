<?php
namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;

class UserInventory extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'userinventories';
}
