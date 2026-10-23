<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One way to pay SAS fees. Staff add online checkout links, invoice-by-email and bank-transfer instructions under Website, Payment options. */
class PaymentOption extends Model
{
    use AuditsChanges;

    public const KINDS = ['online' => 'Online payment (link)', 'invoice' => 'Invoice by email', 'bank' => 'Bank transfer', 'other' => 'Other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('enabled', true)->orderBy('sort')->orderBy('id');
    }
}
