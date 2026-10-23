<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A fee SAS charges a student for a case. Amounts are whole US cents. */
class Invoice extends Model
{
    use AuditsChanges;

    public const KINDS = ['application_fee' => 'Application fee', 'agency_charge' => 'Agency service charge', 'other' => 'Other'];

    public const STATUSES = ['issued' => 'Awaiting payment', 'paid' => 'Paid', 'void' => 'Cancelled'];

    // Money and status only change through CaseService, which records who did it.
    protected $guarded = ['id', 'public_id', 'case_id', 'user_id', 'status', 'refund_status', 'refund_cents', 'paid_at', 'refunded_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'paid_at' => 'datetime', 'refunded_at' => 'datetime', 'due_on' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $i) {
            $i->public_id ??= (string) Str::ulid();
            $i->issued_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class, 'case_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paymentOption(): BelongsTo
    {
        return $this->belongsTo(PaymentOption::class);
    }

    public function amount(): string
    {
        return self::money($this->amount_cents, $this->currency);
    }

    public function refundAmount(): string
    {
        return self::money($this->refund_cents, $this->currency);
    }

    public static function money(int $cents, string $currency = 'USD'): string
    {
        return ($currency === 'USD' ? 'US$' : $currency.' ').number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    }
}
