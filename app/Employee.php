<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'f_name','l_name','address','dob','joined_date','salary','dept_id', 'email', 'password',
    ];
    protected $hidden = [
        'password', 'remember_token','api_token'
    ];
}
