<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\ArticleRevision;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageArticles extends ManageRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data) => $this->guardStatus($data) + ['author_id' => auth()->id()])
                ->after(fn (Model $record) => $this->revision($record)),
        ];
    }

    /** Editors cannot publish, even by tampering with the request (AUTH-05). */
    public static function guardStatus(array $data): array
    {
        if (($data['status'] ?? null) === 'published' && ! auth()->user()->can('content.publish')) {
            $data['status'] = 'in_review';
        }
        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    public static function revision(Model $article): void
    {
        ArticleRevision::create(['article_id' => $article->id, 'user_id' => auth()->id(), 'title' => $article->title, 'body' => $article->body]);
    }
}
