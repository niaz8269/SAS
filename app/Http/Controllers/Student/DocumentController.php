<?php

namespace App\Http\Controllers\Student;

use App\Domain\Documents\DocumentService;
use App\Domain\Documents\PhotosToPdf;
use App\Domain\Editorial\AuditLog;
use App\Enums\ScanStatus;
use App\Http\Controllers\Concerns\ActsForStudent;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAssociation;
use App\Models\DocumentKind;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** FILE-01..03 / SEC-03. Upload, list, preview, download and delete are authorised independently. */
class DocumentController extends Controller
{
    use ActsForStudent;

    public function index(Request $request, DocumentService $service)
    {
        $documents = $this->student($request)->documents()->with(['versions', 'currentVersion', 'associations', 'comments.author'])->latest()->get();
        $used = $documents->flatMap->versions->where('scan_status', '!=', ScanStatus::Rejected)->sum('size');

        return view('student.documents.index', [
            'documents' => $documents, 'used' => $used, 'quota' => config('sas.uploads.quota_mb') * 1024 * 1024,
        ]);
    }

    public function store(Request $request, DocumentService $service)
    {
        // One file ("file") or several at once ("files[]"): each becomes its own document of the chosen type.
        $data = $request->validate([
            'file' => ['required_without:files', 'file'],
            'files' => ['required_without:file', 'array', 'max:10'],
            'files.*' => ['file'],
            'type' => ['required', Rule::in(array_keys(DocumentKind::options()))],
            'title' => ['nullable', 'string', 'max:190'],
            'acknowledge' => ['accepted'],
        ], ['acknowledge.accepted' => 'Please confirm you understand how your document is stored.', 'files.max' => 'Please upload at most 10 files at a time.']);

        $files = array_values(array_filter(array_merge(Arr::wrap($request->file('files')), Arr::wrap($request->file('file')))));
        if ($request->boolean('combine') && count($files) > 1) { // several photos of one document become one PDF
            try {
                $files = [app(PhotosToPdf::class)->make($files, Str::slug(DocumentKind::label($data['type'])).'-photos.pdf')];
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['files' => $e->getMessage()]);
            }
            $data['title'] = $data['title'] ?? null;
        }
        foreach ($files as $file) {
            $title = count($files) === 1 && filled($data['title'] ?? null) ? $data['title'] : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $service->store($this->student($request), $file, $data['type'], $title); // an agent uploads for the student they look after
        }

        return back()->with('status', (count($files) === 1 ? 'Uploaded.' : count($files).' files uploaded.').' Each file is checked for malware before it becomes available.');
    }

    public function storeVersion(Request $request, Document $document, DocumentService $service)
    {
        $this->authorize('update', $document);
        $request->validate(['file' => ['required', 'file']]);
        $service->store($document->user, $request->file('file'), $document->type, $document->title, $document);

        return back()->with('status', 'New version uploaded and queued for scanning.');
    }

    /** The blank form of a document type (for example the CSC application form) that the student downloads, fills in and uploads. */
    public function template(DocumentKind $kind)
    {
        $disk = Storage::disk('local');
        abort_unless($kind->active && $kind->template_path && $disk->exists($kind->template_path), 404);

        return $disk->download($kind->template_path, Str::slug($kind->name).'.'.pathinfo($kind->template_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function download(Request $request, Document $document, DocumentService $service)
    {
        $this->authorize('download', $document);
        $version = $document->currentVersion;
        abort_unless($version && $version->isDownloadable(), 404); // pending or rejected files cannot be downloaded

        AuditLog::record('document.downloaded', $document, null, ['version' => $version->version], null, $request->user()); // path/contents never logged

        $disk = $service->disk();
        $path = $service->pathFor($version);
        $headers = ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => $version->mime];

        // Object storage: a short-lived signed URL after the policy check. Local disks stream through the app.
        if (config('filesystems.disks.'.config('sas.uploads.disk').'.driver') === 's3') {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(config('sas.uploads.download_ttl_minutes')), [
                'ResponseContentDisposition' => 'attachment; filename="'.addslashes($version->original_name).'"',
            ]));
        }

        return response()->streamDownload(function () use ($disk, $path) {
            fpassthru($disk->readStream($path));
        }, $version->original_name, $headers);
    }

    public function destroy(Request $request, Document $document, DocumentService $service)
    {
        $this->authorize('delete', $document);
        $refs = DocumentAssociation::where('document_id', $document->id)->count();
        if ($refs > 0 && ! $request->boolean('confirm')) {
            return back()->with('error', "This document is linked to {$refs} plan(s). Tick the confirmation to delete it and remove those links.");
        }
        AuditLog::record('document.deleted', $document, ['title' => $document->title], null, null, $request->user());
        $service->delete($document);

        $response = $request->boolean('back') ? back() : redirect()->route('student.documents.index'); // the checklist asks to come back to the same page

        return $response->with('status', 'Document deleted.');
    }
}
