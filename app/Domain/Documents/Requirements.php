<?php

namespace App\Domain\Documents;

use App\Enums\ScanStatus;
use App\Models\Document;
use App\Models\DocumentKind;
use App\Models\ScholarshipCycle;
use App\Models\ServiceCase;
use Illuminate\Support\Collection;

/**
 * Which documents a case needs. Per document kind the most specific matching rule wins
 * (scholarship + degree level, then scholarship, then degree level), else the kind's own default.
 */
class Requirements
{
    /**
     * @return Collection<int,array{kind:DocumentKind,requirement:string,note:?string,documents:Collection,state:string}>
     *                                                                                                                    state: missing | scanning | ready (a rejected scan counts as missing)
     */
    public function forCase(ServiceCase $case, ?array $onlyDocumentIds = null): Collection
    {
        return $this->forScholarship($case->cycle, $case->degree_level_id, $case->user_id, $onlyDocumentIds);
    }

    /** The list for a student's application to a scholarship call (a plan or a case), whichever the student started. */
    public function forScholarship(?ScholarshipCycle $cycle, ?int $levelId, int $userId, ?array $onlyDocumentIds = null): Collection
    {
        $programmeId = $cycle?->programme_id;
        $levelId = $this->effectiveLevel($cycle, $levelId);
        $documents = Document::where('user_id', $userId)->when($onlyDocumentIds !== null, fn ($q) => $q->whereIn('id', $onlyDocumentIds ?: [0]))
            ->with('currentVersion')->get()->groupBy('type'); // staff pass the shared ids: they see only what the student shared

        return DocumentKind::where('active', true)->with('rules')->orderBy('sort')->orderBy('name')->get()
            ->map(function (DocumentKind $kind) use ($programmeId, $levelId, $documents) {
                $candidates = $kind->rules
                    ->filter(fn ($r) => ($r->programme_id === null || $r->programme_id === $programmeId) && ($r->degree_level_id === null || $r->degree_level_id === $levelId))
                    ->sortByDesc(fn ($r) => [$r->specificity(), $r->id]);
                // A rule saying "not listed in the official requirements" only fills a gap: SAS's own rule for the degree level still applies.
                $rule = $candidates->first(fn ($r) => ! $r->isNotListed()) ?? $candidates->first();
                $mine = $documents->get($kind->key, collect());

                return [
                    'kind' => $kind,
                    'requirement' => $rule?->requirement ?? $kind->default_requirement,
                    'note' => $rule?->note,
                    'source' => $rule?->source_url,
                    'ruled' => $rule !== null && ! $rule->isNotListed(), // the scholarship itself says something about this document
                    'documents' => $mine,
                    'state' => $this->state($mine),
                ];
            })
            ->reject(fn ($row) => $row['requirement'] === 'not_needed')->values();
    }

    /** The chosen degree level, or the scholarship's only level when it offers just one. */
    public function effectiveLevel(?ScholarshipCycle $cycle, ?int $levelId): ?int
    {
        if ($levelId || ! $cycle) {
            return $levelId;
        }
        $levels = $cycle->degreeLevels;

        return $levels->count() === 1 ? $levels->first()->id : null;
    }

    /** @return array{required:int,ready:int,missing:array<int,string>} Mandatory documents only; scanning counts as provided. */
    public function progress(Collection $rows): array
    {
        $required = $rows->where('requirement', 'required');
        $missing = $required->where('state', 'missing');

        return ['required' => $required->count(), 'ready' => $required->count() - $missing->count(), 'missing' => $missing->map(fn ($r) => $r['kind']->name)->values()->all()];
    }

    private function state(Collection $documents): string
    {
        $states = $documents->map(fn (Document $d) => $d->currentVersion?->scan_status);
        if ($states->contains(ScanStatus::Available)) {
            return 'ready';
        }

        return $states->contains(ScanStatus::Pending) ? 'scanning' : 'missing';
    }
}
