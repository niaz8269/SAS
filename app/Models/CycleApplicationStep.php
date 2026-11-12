<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One step of a scholarship's process as its official source describes it: how to apply, how applicants are selected, or what follows selection. */
class CycleApplicationStep extends Model
{
    public const PHASES = ['apply' => 'How to apply', 'selection' => 'How applicants are selected', 'after_award' => 'After you are selected'];

    protected $guarded = ['id'];
}
