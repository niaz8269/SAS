<?php

namespace App\Domain\System;

use App\Domain\Editorial\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * A backup is one zip with a copy of the database and, when documents live on this server's disk, those files too.
 * It is written to storage/app/backups, the newest 14 are kept. Copy them somewhere else (another computer or cloud storage) as well:
 * a backup that sits on the same disk as the site does not survive that disk failing.
 */
class Backup
{
    public const KEEP = 14;

    public function directory(): string
    {
        return storage_path('app/backups');
    }

    /** @return array{path:string,size:int,files:int} */
    public function run(?User $actor = null): array
    {
        File::ensureDirectoryExists($this->directory());
        $path = $this->directory().DIRECTORY_SEPARATOR.'sas-backup-'.now()->format('Ymd-His').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Could not create the backup file.');
        }
        $temp = [];
        $files = 0;

        $driver = DB::getDriverName();
        if ($driver === 'sqlite' && DB::transactionLevel() === 0) {
            $dump = tempnam(sys_get_temp_dir(), 'sas-db-');
            @unlink($dump); // VACUUM INTO needs a path that does not exist yet
            DB::statement('VACUUM INTO '.DB::getPdo()->quote($dump));
            $zip->addFile($dump, 'database.sqlite');
            $temp[] = $dump;
        } elseif ($driver === 'sqlite') {
            // A transaction is open (only the automated tests do this), and SQLite cannot copy itself then: write every table as JSON instead.
            $tables = collect(DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"))->pluck('name');
            $zip->addFromString('database.json', $tables->mapWithKeys(fn ($t) => [$t => DB::table($t)->get()])->toJson());
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $dump = tempnam(sys_get_temp_dir(), 'sas-db-');
            $cfg = config('database.connections.'.config('database.default'));
            $cmd = sprintf('mysqldump --single-transaction --host=%s --port=%s --user=%s %s > %s', escapeshellarg($cfg['host']), escapeshellarg((string) $cfg['port']), escapeshellarg($cfg['username']), escapeshellarg($cfg['database']), escapeshellarg($dump));
            putenv('MYSQL_PWD='.$cfg['password']);
            exec($cmd, $out, $code);
            putenv('MYSQL_PWD');
            if ($code !== 0) {
                $zip->close();
                @unlink($path);
                throw new RuntimeException('mysqldump failed. Install it on the server or ask the host for database backups.');
            }
            $zip->addFile($dump, 'database.sql');
            $temp[] = $dump;
        } else {
            $zip->close();
            @unlink($path);
            throw new RuntimeException('Backups are not set up for the '.$driver.' database.');
        }

        $disk = config('sas.uploads.disk');
        if (config('filesystems.disks.'.$disk.'.driver') === 'local') {
            $root = Storage::disk($disk)->path('');
            foreach (File::allFiles($root) as $file) {
                $zip->addFile($file->getPathname(), 'documents/'.str_replace('\\', '/', $file->getRelativePathname()));
                $files++;
            }
        }
        $zip->close();
        foreach ($temp as $t) {
            @unlink($t);
        }
        $this->prune();
        AuditLog::record('system.backup', null, null, ['files' => $files, 'size' => filesize($path)], null, $actor);

        return ['path' => $path, 'size' => (int) filesize($path), 'files' => $files];
    }

    /** @return list<array{name:string,size:int,at:Carbon}> newest first */
    public function list(): array
    {
        if (! is_dir($this->directory())) {
            return [];
        }

        return collect(File::files($this->directory()))->filter(fn ($f) => str_ends_with($f->getFilename(), '.zip'))
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'at' => Carbon::createFromTimestamp($f->getMTime())])->sortByDesc('at')->values()->all();
    }

    private function prune(): void
    {
        foreach (array_slice($this->list(), self::KEEP) as $old) {
            @unlink($this->directory().DIRECTORY_SEPARATOR.$old['name']);
        }
    }
}
