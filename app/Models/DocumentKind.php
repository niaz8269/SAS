<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A type of document students upload (passport, transcript...). Whether it is mandatory is decided by DocumentRule, with this default as the fallback. */
class DocumentKind extends Model
{
    use AuditsChanges;

    public const REQUIREMENTS = ['required' => 'Mandatory', 'optional' => 'Optional', 'not_needed' => 'Not needed'];

    /** The document type for the SAS application form: its slot also offers the form filled in with the student's answers. */
    public const APPLICATION_FORM = 'csc_application_form';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(DocumentRule::class, 'kind_id');
    }

    /** @return array<string,string> key => name of every active kind, in display order (the choices on the upload forms). */
    public static function options(): array
    {
        return static::where('active', true)->orderBy('sort')->orderBy('name')->pluck('name', 'key')->all();
    }

    /** The name for a stored type key, also for kinds that were later switched off. */
    public static function label(?string $key): string
    {
        self::$names ??= static::pluck('name', 'key')->all();

        return self::$names[$key] ?? (string) $key;
    }

    /** @var array<string,string>|null names looked up once per request; cleared whenever a kind changes */
    private static ?array $names = null;

    protected static function booted(): void
    {
        $flush = fn () => self::$names = null;
        static::saved($flush);
        static::deleted($flush);
    }
}
