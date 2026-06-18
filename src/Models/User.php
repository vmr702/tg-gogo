<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'telegram_id',
        'username',
        'city',
    ];

    public $timestamps = true;
}
