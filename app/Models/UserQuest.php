<?php
namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;

class UserQuest extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'userquests';
}
