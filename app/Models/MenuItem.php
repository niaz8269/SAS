<?php

namespace App\Models;

use App\Domain\Site\Site;
use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use AuditsChanges;

    public const LOCATIONS = ['header' => 'Header menu', 'footer_explore' => 'Footer: Explore', 'footer_policies' => 'Footer: Policies'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['new_tab' => 'boolean', 'visible' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Site::forget());
        static::deleted(fn () => Site::forget());
    }
}
