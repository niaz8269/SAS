<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CycleRequirement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['mandatory' => 'boolean'];
    }
}
