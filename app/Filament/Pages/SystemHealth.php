<?php

namespace App\Filament\Pages;

use App\Domain\Editorial\AuditLog;
use App\Domain\Site\Site;
use App\Domain\System\Backup;
use App\Domain\System\Health;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
            Action::make('approveLegal')->label('Legal texts approved')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn () => ! config('sas.legal_approved'))
                ->requiresConfirmation()->modalHeading('Approve the legal texts')
                ->modalDescription('Press this only when you, as the owner or with your lawyer, have read the Privacy notice, the Terms and the Cancellation and refund policy and accept them as they are. The "draft" notice on those pages then disappears. This is recorded with your name and the date.')
                ->action(function () {
                    abort_unless(static::canAccess(), 403);
                    Site::save(['legal_approved_at' => now()->toDateTimeString(), 'legal_approved_by' => auth()->user()->name]);
                    AuditLog::record('site.legal_approved', null, null, ['by' => auth()->user()->name]);
                    Notification::make()->title('Legal texts marked as approved')->success()->send();
                }),
            Action::make('withdrawLegal')->label('Withdraw legal approval')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                ->visible(fn () => (bool) Site::get('legal_approved_at'))
                ->requiresConfirmation()->modalDescription('The "draft" notice comes back on the legal pages. Use it when the texts are being changed.')
                ->action(function () {
                    abort_unless(static::canAccess(), 403);
                    Site::save(['legal_approved_at' => null, 'legal_approved_by' => null]);
                    config(['sas.legal_approved' => config('sas.legal_approved_env')]);
                    AuditLog::record('site.legal_approval_withdrawn');
                    Notification::make()->title('Legal approval withdrawn')->warning()->send();
                }),
            Action::make('testEmail')->label('Send a test email to me')->icon('heroicon-o-envelope')->color('gray')
                ->action(function () {
                    abort_unless(static::canAccess(), 403);
                    $to = auth()->user()->email;
                    $logOnly = in_array(config('mail.default'), ['log', 'array'], true);
                    try {
                        Mail::raw('This is a test message from the '.config('sas.brand.short').' website. If you can read it in your inbox (not in spam), email sending is working.', fn ($m) => $m->to($to)->subject(config('sas.brand.short').' test email'));
                        Notification::make()->title($logOnly ? 'Written to the log only' : 'Test email sent to '.$to)
                            ->body($logOnly ? 'Mail is set to "'.config('mail.default').'", so nothing was delivered. Set a real mail service in .env.' : 'Check your inbox and your spam folder.')
                            ->color($logOnly ? 'warning' : 'success')->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('The email could not be sent')->body(Str::limit($e->getMessage(), 300))->danger()->send();
                    }
                }),
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
