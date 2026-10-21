<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CycleCriterion extends Model
{
    protected $table = 'cycle_criteria';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['mandatory' => 'boolean'];
    }
}
