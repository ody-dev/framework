<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $hidden = [
        'password',
        'created_at',
        'updated_at'
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public function posts()
    {
        return $this->hasMany('App\Models\Post');
    }
}