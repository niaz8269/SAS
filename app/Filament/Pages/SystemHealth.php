<?php

namespace App\Filament\Pages;

use App\Domain\System\Backup;
use App\Domain\System\Health;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** For the super admin: is the site healthy, when was the last backup, and a button to take one now. */
class SystemHealth extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'System health';

    protected static ?string $slug = 'system-health';

    protected string $view = 'filament.pages.system-health';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff() && auth()->user()->hasPermission('roles.manage'); // only the super admin
    }

    public static function getNavigationBadge(): ?string
    {
        $bad = collect(app(Health::class)->checks())->where('level', 'bad')->count();

        return $bad ? (string) $bad : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backup')->label('Back up now')->icon('heroicon-o-archive-box-arrow-down')->requiresConfirmation()
                ->modalDescription('Writes a copy of the database and the uploaded documents to the server. It can take a minute.')
                ->action(function () {
                    abort_unless(static::canAccess(), 403);
                    try {
                        $r = app(Backup::class)->run(auth()->user());
                        Notification::make()->title('Backup written')->body(basename($r['path']).' ('.round($r['size'] / 1048576, 1).' MB)')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('The backup failed')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    protected function getViewData(): array
    {
        $health = app(Health::class);

        return ['checks' => $health->checks(), 'counts' => $health->counts(), 'backups' => array_slice(app(Backup::class)->list(), 0, 8)];
    }
}
