<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected  = 'users';
    protected  = 'identification';
    public  = false;

    protected  = [
        'name',
        'last_Name', 
        'email', 
        'phone', 
        'direction',
        'identification',
        'user_Name',
        'password',
        'confirm_Password'
    ];

    protected  = [
        'password',
        'remember_token',
    ];
}
