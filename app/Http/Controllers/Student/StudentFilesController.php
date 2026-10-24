<?php

namespace App\Http\Controllers\Student;

use App\Domain\Documents\DocumentService;
use App\Domain\Editorial\AuditLog;
use App\Http\Controllers\Controller;
use App\Models\DocumentKind;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ZipArchive;

/** A counsellor downloads every document of one of their students as a single zip. Each file is named by its type, and the download is audited. */
class StudentFilesController extends Controller
{
    public function zip(Request $request, Student $student, DocumentService $service)
    {
        abort_unless($request->user()->counsels($student), 403);
        $documents = $student->documents()->with('currentVersion')->get()->filter(fn ($d) => $d->currentVersion?->isDownloadable());
        abort_if($documents->isEmpty(), 404, 'This student has no documents to download.');

        $path = tempnam(sys_get_temp_dir(), 'sas-docs-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $used = [];
        foreach ($documents as $document) {
            $version = $document->currentVersion;
            $ext = pathinfo((string) $version->original_name, PATHINFO_EXTENSION);
            $base = Str::slug(DocumentKind::label($document->type)).'-'.Str::slug(pathinfo((string) $version->original_name, PATHINFO_FILENAME));
            $name = $base.($ext ? '.'.$ext : '');
            for ($i = 2; isset($used[$name]); $i++) {
                $name = $base.'-'.$i.($ext ? '.'.$ext : '');
            }
            $used[$name] = true;
            $zip->addFromString($name, $service->disk()->get($service->pathFor($version)));
        }
        $zip->close();
        AuditLog::record('student.documents_zipped', $student, null, ['files' => count($used)], null, $request->user());

        return response()->download($path, 'SAS-documents-'.Str::slug($student->name).'.zip', ['Cache-Control' => 'private, no-store'])->deleteFileAfterSend();
    }
}
