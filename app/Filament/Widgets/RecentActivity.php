<?php

namespace App\Filament\Widgets;

use App\Models\Audit;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** The latest changes made by staff. Visible to anyone allowed to read the audit trail. */
class RecentActivity extends TableWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent activity';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('audit.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Audit::query()->with('actor')->latest('id')->limit(8))
            ->columns([
                TextColumn::make('created_at')->label('When')->since(),
                TextColumn::make('actor.name')->label('Who')->placeholder('System'),
                TextColumn::make('action')->label('What')->badge()->color('gray'),
                TextColumn::make('reason')->limit(60)->placeholder(''),
            ])
            ->paginated(false);
    }
}
