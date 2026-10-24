<?php

namespace App\Domain\System;

use App\Domain\Site\Site;
use App\Models\Document;
use App\Models\NotificationEvent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Does the site look healthy? Each check is a label, a value, and a level: ok, warn or bad. */
class Health
{
    public function __construct(private Backup $backup) {}

    /** @return list<array{label:string,value:string,level:string,hint:?string}> */
    public function checks(): array
    {
        $beat = Cache::get('sas.heartbeat');
        $beatAge = $beat ? Carbon::parse($beat)->diffInMinutes(now()) : null;
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedReminders = NotificationEvent::where('status', 'failed')->count();
        $backups = $this->backup->list();
        $latest = $backups[0] ?? null;
        $backupHours = $latest ? $latest['at']->diffInHours(now()) : null;
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        $freePercent = $free && $total ? round($free / $total * 100) : null;
        $production = app()->environment('production');

        return [
            ['label' => 'Scheduler', 'value' => $beatAge === null ? 'never ran' : ($beatAge < 1 ? 'running' : 'last ran '.$beatAge.' minutes ago'), 'level' => $beatAge === null ? ($production ? 'bad' : 'warn') : ($beatAge <= 5 ? 'ok' : 'bad'),
                'hint' => 'It sends reminders, the morning emails and backups. On the server, `php artisan schedule:run` must run every minute.'],
            ['label' => 'Failed background jobs', 'value' => (string) $failed, 'level' => $failed ? 'bad' : 'ok', 'hint' => $failed ? 'Run `php artisan queue:failed` to see them.' : null],
            ['label' => 'Waiting background jobs', 'value' => (string) $pending, 'level' => $pending > 200 ? 'warn' : 'ok', 'hint' => null],
            ['label' => 'Failed reminders', 'value' => (string) $failedReminders, 'level' => $failedReminders ? 'warn' : 'ok', 'hint' => null],
            ['label' => 'Latest backup', 'value' => $latest ? $latest['name'].' ('.$latest['at']->diffForHumans().', '.round($latest['size'] / 1048576, 1).' MB)' : 'none yet', 'level' => $latest === null ? ($production ? 'bad' : 'warn') : ($backupHours > 36 ? 'bad' : 'ok'),
                'hint' => 'Copy backups to another place too: a backup on the same disk does not survive that disk failing.'],
            ['label' => 'Free disk space', 'value' => $freePercent === null ? 'unknown' : $freePercent.'% free ('.round($free / 1073741824, 1).' GB)', 'level' => $freePercent === null ? 'warn' : ($freePercent < 10 ? 'bad' : ($freePercent < 20 ? 'warn' : 'ok')), 'hint' => null],
            ['label' => 'Two-step sign-in', 'value' => config('sas.two_step.enabled') ? 'on' : 'off', 'level' => config('sas.two_step.enabled') ? 'ok' : ($production ? 'warn' : 'ok'), 'hint' => config('sas.two_step.enabled') ? null : 'Staff sign in with a password only. Set SAS_TWO_STEP=true before the site is public.'],
            ['label' => 'Debug mode', 'value' => config('app.debug') ? 'on' : 'off', 'level' => config('app.debug') && $production ? 'bad' : 'ok', 'hint' => config('app.debug') && $production ? 'APP_DEBUG must be false on a live site: error pages can show secrets.' : null],
            ['label' => 'Mail', 'value' => config('mail.default'), 'level' => in_array(config('mail.default'), ['log', 'array'], true) && $production ? 'bad' : 'ok', 'hint' => in_array(config('mail.default'), ['log', 'array'], true) ? 'Emails are written to a log file, not sent. Set a real mail service in .env, then use "Send a test email to me" above.' : 'Use "Send a test email to me" above and check the spam folder.'],
            $this->queueCheck($production),
            ['label' => 'Legal texts', 'value' => config('sas.legal_approved') ? 'approved'.(Site::get('legal_approved_by') ? ' by '.Site::get('legal_approved_by') : '') : 'still marked as draft', 'level' => config('sas.legal_approved') ? 'ok' : ($production ? 'warn' : 'ok'),
                'hint' => config('sas.legal_approved') ? null : 'The privacy, terms and refund pages show a draft notice. When you have approved them, use "Legal texts approved" above.'],
            ...$this->senderRecordChecks($production),
        ];
    }

    /** Jobs that sit waiting for a long time mean nothing is working the queue (no worker, and the cron line is not running). */
    private function queueCheck(bool $production): array
    {
        $sync = config('queue.default') === 'sync';
        $oldest = (! $sync && Schema::hasTable('jobs')) ? DB::table('jobs')->whereNull('reserved_at')->min('available_at') : null;
        $minutes = $oldest ? (int) floor((time() - (int) $oldest) / 60) : 0;

        return ['label' => 'Background jobs (emails)', 'value' => $sync ? 'sent straight away (no queue)' : ($minutes >= 10 ? 'waiting for '.$minutes.' minutes' : 'being worked'), 'level' => $minutes >= 10 ? 'bad' : 'ok',
            'hint' => $minutes >= 10 ? 'Emails are stuck. The one cron line (`schedule:run` every minute) works the queue when SAS_QUEUE_VIA_CRON is true; otherwise run a queue worker.' : null];
    }

    /**
     * SPF and DMARC records of the domain emails are sent from (a missing one sends mail to spam). DKIM cannot be checked from outside without
     * the provider's selector, so the hint asks for it. Only on a live site; looked up at most every 10 minutes.
     *
     * @return list<array{label:string,value:string,level:string,hint:?string}>
     */
    private function senderRecordChecks(bool $production): array
    {
        $domain = strtolower(Str::after((string) config('mail.from.address'), '@'));
        if (! $production || $domain === '' || ! str_contains($domain, '.')) {
            return [];
        }
        $found = Cache::remember('sas.health.dns.'.$domain, 600, function () use ($domain) {
            $txt = fn (string $host): array => array_map(fn ($r) => strtolower((string) ($r['txt'] ?? '')), (self::$dns ?? fn (string $h) => @dns_get_record($h, DNS_TXT))($host) ?: []);

            return ['spf' => (bool) array_filter($txt($domain), fn ($t) => str_starts_with($t, 'v=spf1')), 'dmarc' => (bool) array_filter($txt('_dmarc.'.$domain), fn ($t) => str_starts_with($t, 'v=dmarc1'))];
        });

        return [
            ['label' => 'Email sender record (SPF)', 'value' => $found['spf'] ? 'found for '.$domain : 'not found for '.$domain, 'level' => $found['spf'] ? 'ok' : 'warn', 'hint' => $found['spf'] ? 'Also ask your mail provider to confirm the DKIM record is active.' : 'Add the SPF record your mail provider gives you in your domain\'s DNS settings, or emails land in spam.'],
            ['label' => 'Email sender record (DMARC)', 'value' => $found['dmarc'] ? 'found' : 'not found', 'level' => $found['dmarc'] ? 'ok' : 'warn', 'hint' => $found['dmarc'] ? null : 'Add a TXT record named _dmarc with: v=DMARC1; p=none; rua=mailto:'.config('sas.contact_email')],
        ];
    }

    /** Test hook: a callable (host) => dns_get_record result. */
    public static ?\Closure $dns = null;

    /** @return array<string,int|string> */
    public function counts(): array
    {
        return [
            'Students' => Student::count(), 'Staff' => User::whereHas('userRoles', fn ($q) => $q->whereIn('role', config('sas.staff_roles')))->count(),
            'Documents' => Document::count(), 'Database size' => $this->databaseSize(),
        ];
    }

    private function databaseSize(): string
    {
        if (DB::getDriverName() === 'sqlite' && is_file($path = (string) config('database.connections.sqlite.database'))) {
            return round(filesize($path) / 1048576, 1).' MB';
        }

        return 'n/a';
    }
}
