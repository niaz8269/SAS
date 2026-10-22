<?php

namespace App\Filament\Support;

use Closure;

/** Links staff type into the admin must be same-site paths, http(s), mailto or tel. Never javascript: or data:. */
class SafeUrl
{
    /** @return list<Closure> */
    public static function rules(): array
    {
        return [fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
            if (filled($value) && ! preg_match('~^(/(?!/)|https?://|mailto:|tel:)~i', trim((string) $value))) {
                $fail('Use a path such as /about, or a full link starting with https://, mailto: or tel:.');
            }
        }];
    }
}
