<?php
namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;

class Promo extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'promos';
}
