<?php

namespace App\Domain\Identity;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Proves a person owns the email address they signed up with: a 6-digit code is emailed and must be typed back within 15 minutes.
 * A code is single-use, tied to the address it was sent to, allows 5 wrong tries, and is stored only as a keyed hash.
 */
class EmailCodes
{
    public const TTL_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const MAIL_ERROR = 'We could not send the email just now. Your account is saved. Please wait a minute and press "Send me a new code"; if it keeps failing, contact us and we will help you.';

    private bool $mailFailed = false;

    /** Emails a fresh code. Returns false (and sends nothing) when one was sent less than a minute ago, unless $force. */
    public function issue(User $user, bool $force = false): bool
    {
        $row = DB::table('email_verification_codes')->where('user_id', $user->id)->first();
        if ($row && ! $force && Carbon::parse($row->sent_at)->gt(now()->subSeconds(self::RESEND_SECONDS))) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('email_verification_codes')->updateOrInsert(['user_id' => $user->id], [
            'email' => mb_strtolower($user->email), 'code_hash' => $this->hash($user, $code), 'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES), 'sent_at' => now(),
        ]);
        $this->mailFailed = false;
        try {
            $user->notify(new VerifyEmailCode($code, self::TTL_MINUTES));
        } catch (\Throwable $e) {
            // The mail service is down or refusing us: sign-up must not crash. The person is told, and can ask for a new code.
            report($e);
            $this->mailFailed = true;
            if (app()->bound('request') && request()->hasSession()) {
                request()->session()->flash('mail_error', self::MAIL_ERROR);
            }
        }

        return true;
    }

    /** True when the last issue() could not hand the email to the mail service. */
    public function lastSendFailed(): bool
    {
        return $this->mailFailed;
    }

    public function hasLiveCode(User $user): bool
    {
        return DB::table('email_verification_codes')->where('user_id', $user->id)
            ->where('expires_at', '>', now())->where('attempts', '<', self::MAX_ATTEMPTS)->exists();
    }

    /** Checks the typed code; on success the address counts as verified and the code is used up. */
    public function verify(User $user, string $input): bool
    {
        $input = preg_replace('/\D/', '', $input) ?? '';
        $row = DB::table('email_verification_codes')->where('user_id', $user->id)->first();
        if (! $row || Carbon::parse($row->expires_at)->lte(now()) || $row->attempts >= self::MAX_ATTEMPTS || $row->email !== mb_strtolower($user->email)) {
            return false;
        }
        if (strlen($input) !== 6 || ! hash_equals($row->code_hash, $this->hash($user, $input))) {
            DB::table('email_verification_codes')->where('user_id', $user->id)->increment('attempts');

            return false;
        }

        DB::table('email_verification_codes')->where('user_id', $user->id)->delete();
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return true;
    }

    private function hash(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.mb_strtolower($user->email).'|'.$code, (string) config('app.key'));
    }
}
