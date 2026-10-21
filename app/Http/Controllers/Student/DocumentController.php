<?php

namespace App\Http\Controllers\Student;

use App\Domain\Documents\DocumentService;
use App\Domain\Editorial\AuditLog;
use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAssociation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** FILE-01..03 / SEC-03. Upload, list, preview, download and delete are authorised independently. */
class DocumentController extends Controller
{
    public function index(Request $request, DocumentService $service)
    {
        $documents = $request->user()->documents()->with(['versions', 'currentVersion', 'associations'])->latest()->get();
        $used = $documents->flatMap->versions->where('scan_status', '!=', ScanStatus::Rejected)->sum('size');

        return view('student.documents.index', [
            'documents' => $documents, 'used' => $used, 'quota' => config('sas.uploads.quota_mb') * 1024 * 1024,
        ]);
    }

    public function store(Request $request, DocumentService $service)
    {
        $data = $request->validate([
            'file' => ['required', 'file'],
            'type' => ['required', Rule::in(array_keys(config('sas.document_types')))],
            'title' => ['nullable', 'string', 'max:190'],
            'acknowledge' => ['accepted'],
        ], ['acknowledge.accepted' => 'Please confirm you understand how your document is stored.']);

        $file = $request->file('file');
        $service->store($request->user(), $file, $data['type'], $data['title'] ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return back()->with('status', 'Uploaded. The file is checked for malware before it becomes available.');
    }

    public function storeVersion(Request $request, Document $document, DocumentService $service)
    {
        $this->authorize('update', $document);
        $request->validate(['file' => ['required', 'file']]);
        $service->store($request->user(), $request->file('file'), $document->type, $document->title, $document);

        return back()->with('status', 'New version uploaded and queued for scanning.');
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
        if (method_exists($disk, 'providesTemporaryUrls') && $disk->providesTemporaryUrls()) {
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

        return redirect()->route('student.documents.index')->with('status', 'Document deleted.');
    }
}
