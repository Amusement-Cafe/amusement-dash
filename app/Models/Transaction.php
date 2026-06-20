<?php
namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;

class Transaction extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'transactions';
}
