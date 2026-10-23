<?php

namespace App\Domain\Partners;

use App\Domain\Editorial\AuditLog;
use App\Domain\Editorial\ScholarshipImporter;
use App\Models\PartnerSubmission;
use App\Models\ScholarshipCycle;
use App\Models\User;
use InvalidArgumentException;

/**
 * Turns an accepted partner submission into a DRAFT scholarship through the normal CSV importer, so it follows the usual rules:
 * a draft only, evidence required, second-person review before anything is published. A partner is never treated as a funder's
 * official source on its own: agents count as "aggregator" evidence, which the editors must replace with the official page.
 */
class PartnerSubmissionService
{
    public function decide(PartnerSubmission $s, string $status, ?string $editorNote, ?User $actor = null): PartnerSubmission
    {
        if (! array_key_exists($status, PartnerSubmission::STATUSES)) {
            throw new InvalidArgumentException('Unknown status.');
        }
        if ($status === 'needs_changes' && blank($editorNote)) {
            throw new InvalidArgumentException('Tell the partner what to change.');
        }
        $before = $s->status;
        $s->forceFill(['status' => $status, 'editor_note' => filled($editorNote) ? trim($editorNote) : $s->editor_note])->save();
        AuditLog::record('partner.submission_decided', $s, ['status' => $before], ['status' => $status], null, $actor);

        return $s;
    }

    /** @throws InvalidArgumentException with the importer's own messages when a row is not valid */
    public function createDraft(PartnerSubmission $s, ?User $actor = null): ScholarshipCycle
    {
        if ($s->cycle_id) {
            throw new InvalidArgumentException('A draft was already created from this submission.');
        }
        $org = $s->organisation;
        $key = 'partner-'.$org->id.'-'.$s->id;
        $row = [
            $key, $s->title, $s->funder_name ?: $org->name, $s->country?->code ?? '', (string) ($s->cycle_year ?: now()->year + 1), (string) $s->intake_term, $s->funding_type ?: 'unknown',
            (string) $s->funding_summary, (string) $s->teaching_language, 'unknown', implode('|', $s->degree_codes ?? []), $s->country?->code ?? '', '', '', (string) $s->summary,
            $s->official_url, $org->name, in_array($org->kind, ['university', 'funder'], true) ? $org->kind : 'aggregator', now()->toDateString(), '', '',
            $s->deadline_on ? 'date_only' : 'not_announced', '', '', $s->deadline_on?->toDateString() ?? '', (string) $s->deadline_note,
        ];

        $path = tempnam(sys_get_temp_dir(), 'partner');
        file_put_contents($path, ScholarshipImporter::csvLine(ScholarshipImporter::COLUMNS).ScholarshipImporter::csvLine($row));
        try {
            $importer = app(ScholarshipImporter::class);
            $import = $importer->stage($path, 'partner-submission-'.$s->public_id.'.csv', $actor);
            if ($import->status === 'failed' || $import->rows()->where('status', 'invalid')->exists()) {
                $errors = $import->rows()->where('status', 'invalid')->get()->flatMap(fn ($r) => $r->errors ?? [])->all() ?: [($import->summary['error'] ?? 'The submission could not be checked.')];
                throw new InvalidArgumentException(implode(' ', $errors));
            }
            $importer->commit($import, $actor);
        } finally {
            @unlink($path);
        }

        $cycle = ScholarshipCycle::where('external_key', $key)->first();
        if (! $cycle) {
            throw new InvalidArgumentException('The draft could not be created.');
        }
        $s->forceFill(['cycle_id' => $cycle->id, 'status' => 'accepted'])->save();
        AuditLog::record('partner.submission_drafted', $s, null, ['cycle_id' => $cycle->id], null, $actor);

        return $cycle;
    }
}
