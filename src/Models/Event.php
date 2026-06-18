<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'user_id',
        'city',
        'title',
        'description',
    ];

    public $timestamps = true;
}
