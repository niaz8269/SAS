<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What one programme costs at one university for one intake: only what the university or the government charges. */
class UniversityFeeStructure extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['other_fees' => 'array', 'required_documents' => 'array', 'notes' => 'array', 'published' => 'boolean', 'source_date' => 'date', 'duration_years' => 'float'];
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /** Tuition for one year (a per-semester fee counts twice). */
    public function tuitionPerYear(bool $later = false): ?int
    {
        $amount = $later ? ($this->tuition_amount_later ?? $this->tuition_amount) : $this->tuition_amount;

        return $amount === null ? null : $amount * ($this->tuition_period === 'semester' ? 2 : 1);
    }

    /**
     * What the first year and each later year cost in the university's own charges (tuition, hostel and the listed fees), without refundable
     * deposits and without the application fee, which is paid before admission. `from` is true when a listed fee is a range, so the total is a minimum.
     *
     * @return array{first:?int,later:?int,from:bool}
     */
    public function totals(): array
    {
        $first = $this->tuitionPerYear();
        $later = $this->tuitionPerYear(true);
        if ($first === null) {
            return ['first' => null, 'later' => null, 'from' => false];
        }
        $from = false;
        $first += (int) $this->hostel_amount;
        $later += (int) ($this->hostel_amount_later ?? $this->hostel_amount);
        foreach ($this->other_fees ?? [] as $fee) {
            if (! empty($fee['refundable']) || ! empty($fee['optional']) || ! isset($fee['amount'])) {
                continue;
            }
            $from = $from || ! empty($fee['amount_max']);
            $first += (int) $fee['amount'];
            if (($fee['when'] ?? 'every_year') === 'every_year') {
                $later += (int) $fee['amount'];
            }
        }

        return ['first' => $first, 'later' => $later, 'from' => $from];
    }

    public function money(?int $amount): string
    {
        return $amount === null ? 'Not stated' : $this->currency.' '.number_format($amount);
    }

    public function sourceLabelText(): string
    {
        return match ($this->source_type) {
            'supplied' => 'Fee sheet given to SAS'.($this->source_date ? ', '.$this->source_date->format('F Y') : ''),
            default => ($this->source_label ?: 'University page').($this->source_date ? ', read '.$this->source_date->format('j F Y') : ''),
        };
    }
}
