<?php

namespace App\Models;

use App\Support\Html;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BlogPost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'featured' => 'boolean'];
    }

    /** Published and not scheduled for later, newest first. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->where('published_at', '<=', now())->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->gt(now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        // Pictures that ship with the site live in public/images; pictures uploaded in the admin live in the media storage.
        return str_starts_with($this->image_path, 'images/') ? asset($this->image_path) : Storage::disk('public_media')->url($this->image_path);
    }

    /** About 200 words a minute, never less than one. */
    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(Html::text($this->body)) / 200));
    }

    /** The excerpt, or the first words of the post. */
    public function summary(int $limit = 160): string
    {
        return Html::text($this->excerpt ?: $this->body, $limit);
    }
}
