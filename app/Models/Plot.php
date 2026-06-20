<?php
namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;

class Plot extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'plots';
}
