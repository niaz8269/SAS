<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSuppression extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['lifted_at' => 'datetime'];
    }
}
