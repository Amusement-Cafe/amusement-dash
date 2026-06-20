<?php
namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;

class Hero extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'heros';
}
