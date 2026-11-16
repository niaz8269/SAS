<?php

namespace App\Filament\Widgets;

use App\Models\Audit;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Str;

/** The latest changes made by staff. Visible to anyone allowed to read the audit trail. */
class RecentActivity extends TableWidget
{
    protected static ?int $sort = 5;

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
            ->query(Audit::query()->with('actor')->latest('id')->limit(6))
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->color('gray'),
                TextColumn::make('actor.name')->label('Who')->placeholder('System'),
                // "application_form.saved" reads as "Application form saved": the stored key is for developers, not for the person looking at the dashboard.
                TextColumn::make('action')->label('What')->formatStateUsing(fn (string $state) => Str::ucfirst(str_replace(['.', '_'], ' ', $state))),
                TextColumn::make('reason')->limit(60)->placeholder('')->color('gray'),
            ])
            ->paginated(false);
    }
}
