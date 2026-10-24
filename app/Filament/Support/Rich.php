<?php

namespace App\Filament\Support;

use App\Support\Html;
use Filament\Forms\Components\RichEditor;

/**
 * The editor used for every long public text in the admin (introductions, summaries, notes). Same formatting everywhere: bold, italic,
 * underline, links, two heading sizes, bullet and numbered lists, quotes. What is saved is cleaned again when it is shown on the website
 * (App\Support\Html), so pasted scripts or odd markup never run.
 */
class Rich
{
    public static function make(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([['bold', 'italic', 'underline', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']])
            ->dehydrateStateUsing(fn ($state) => is_string($state) && Html::isBlank($state) ? null : $state) // an empty editor saves as nothing, not as an empty paragraph
            ->columnSpanFull();
    }
}
