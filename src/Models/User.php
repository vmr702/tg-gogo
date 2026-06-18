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
        'step',
        'draft_event',
    ];

    protected $casts = [
        'draft_event' => 'array',
    ];

    public $timestamps = true;
}
