<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Testimonial extends Model
{
    use AuditsChanges;

    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Prefer not to say'];

    protected $guarded = ['id'];

    /** Entries typed in by staff are already reviewed; stories sent from the website are saved as pending. */
    protected $attributes = ['review_status' => 'approved', 'source' => 'staff'];

    protected function casts(): array
    {
        return ['consent_given' => 'boolean', 'published' => 'boolean', 'consent_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Whatever the form or any other code does, an entry without recorded consent, or one staff have not approved, can never be public.
        static::saving(function (self $t) {
            if (! $t->consent_given || ! $t->consent_date || $t->review_status !== 'approved') {
                $t->published = false;
            }
        });
        static::deleting(function (self $t) {
            if ($t->photo_path && str_starts_with($t->photo_path, 'stories/')) {
                Storage::disk('public_media')->delete($t->photo_path); // a photo sent by the person goes with the story
            }
        });
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('published', true)->where('consent_given', true)->whereNotNull('consent_date')->where('review_status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('review_status', 'pending');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The person's photo, or a sketch that differs for men and women (a neutral one when they preferred not to say). */
    public function avatarUrl(): string
    {
        if ($this->photo_path) {
            return Storage::disk('public_media')->url($this->photo_path);
        }

        // the number changes whenever the pictures are redrawn, so browsers do not keep showing the old ones
        return asset('images/avatars/'.match ($this->gender) {
            'male' => 'male', 'female' => 'female', default => 'neutral',
        }.'.svg').'?v=2';
    }

    public function hasPhoto(): bool
    {
        return (bool) $this->photo_path;
    }

    public function hasStory(): bool
    {
        return filled($this->story) && filled($this->slug);
    }

    /** "University, country of the scholarship" (and the older free-text context for entries typed in by staff). */
    public function placeLine(): string
    {
        $line = collect([$this->university, $this->study_country])->filter()->implode(', ');

        return $line !== '' ? $line : (string) $this->context;
    }

    public function excerpt(int $limit = 220): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/', ' ', $this->quote ?: $this->story)), $limit);
    }

    public static function newSlug(string $name): string
    {
        do {
            $slug = Str::slug(Str::limit($name, 40, '')).'-'.Str::lower(Str::random(6));
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    /** Approving publishes it: the person's consent was recorded when they sent it. */
    public function approve(?User $by = null): void
    {
        $this->forceFill(['review_status' => 'approved', 'published' => true, 'reviewed_by' => $by?->id, 'reviewed_at' => now()])->save();
    }

    public function reject(?string $note = null, ?User $by = null): void
    {
        $this->forceFill(['review_status' => 'rejected', 'published' => false, 'review_note' => $note, 'reviewed_by' => $by?->id, 'reviewed_at' => now()])->save();
    }
}
