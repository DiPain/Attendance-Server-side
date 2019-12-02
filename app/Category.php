<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public function policies()
    {
        return $this->hasMany(Policy::class);
    }
}
