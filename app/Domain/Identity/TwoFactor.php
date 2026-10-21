<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

/** Optional TOTP for students, and the challenge applied to every account that has an authenticator (staff included). Shares columns with Filament's panel MFA. */
class TwoFactor
{
    public function __construct(private Google2FA $google2fa) {}

    public function enabled(User $user): bool
    {
        return filled($user->app_authentication_secret);
    }

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey(16);
    }

    public function qrCode(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeInline(config('sas.brand.short'), $user->email, $secret);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey($secret, preg_replace('/\s+/', '', $code), 1);
    }

    /** @return list<string> */
    public function newRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => strtolower(Str::random(5).'-'.Str::random(5)))->all();
    }

    public function enable(User $user, string $secret): array
    {
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['app_authentication_secret' => $secret, 'app_authentication_recovery_codes' => $codes])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
    }

    /** Accepts a current code or a single-use recovery code. */
    public function challenge(User $user, string $input): bool
    {
        $input = trim($input);
        if (preg_match('/^\d[\d\s]{5,}$/', $input)) {
            return $this->verifyCode((string) $user->app_authentication_secret, $input);
        }
        $codes = $user->app_authentication_recovery_codes ?? [];
        $match = collect($codes)->first(fn ($c) => hash_equals((string) $c, strtolower($input)));
        if ($match === null) {
            return false;
        }
        $user->forceFill(['app_authentication_recovery_codes' => array_values(array_diff($codes, [$match]))])->save(); // single use

        return true;
    }
}
