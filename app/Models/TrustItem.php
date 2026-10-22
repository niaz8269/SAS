<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;

class TrustItem extends Model
{
    use AuditsChanges;

    public const KINDS = ['point' => 'Promise / fact', 'partner' => 'Partner or accreditation logo'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
