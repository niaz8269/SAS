<?php

namespace App\Domain\Editorial;

use App\Models\CycleReview;
use App\Models\CycleRevision;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staged edits to a live cycle. The public page keeps showing the verified version until a person other than the proposer
 * approves the change. An approval applies it atomically; if the live cycle changed in the meantime the proposal is marked
 * outdated instead of overwriting newer work.
 */
class CycleRevisions
{
    /** Scalar columns covered by revisions. Placement flags are not: they are not factual claims about the award. */
    public const FIELDS = ['programme_id', 'cycle_key', 'slug', 'intake_year', 'intake_term', 'funding_type', 'fully_funded_verified', 'funding_summary',
        'teaching_language', 'delivery_mode', 'any_nationality', 'summary', 'eligibility_notes', 'age_experience_notes'];

    /** Relation name => pivot id list. */
    public const RELATIONS = ['destinations', 'nationalities', 'degreeLevels', 'subjects', 'universities'];

    public function pending(ScholarshipCycle $cycle): ?CycleRevision
    {
        return CycleRevision::where('cycle_id', $cycle->id)->where('status', 'pending')->latest('id')->first();
    }

    /** @return array{fields:array<string,mixed>,relations:array<string,list<int>>} */
    public function snapshot(ScholarshipCycle $cycle): array
    {
        $fields = [];
        foreach (self::FIELDS as $f) {
            $fields[$f] = $cycle->getRawOriginal($f) ?? $cycle->{$f};
            if (is_bool($fields[$f]) || in_array($f, ['fully_funded_verified', 'any_nationality'], true)) {
                $fields[$f] = (bool) $fields[$f];
            }
        }
        $relations = [];
        foreach (self::RELATIONS as $r) {
            $relations[$r] = $cycle->{$r}()->pluck($cycle->{$r}()->getRelated()->getTable().'.id')->map(fn ($i) => (int) $i)->sort()->values()->all();
        }

        return ['fields' => $fields, 'relations' => $relations];
    }

    public function hash(ScholarshipCycle $cycle): string
    {
        return hash('sha256', json_encode($this->snapshot($cycle)));
    }

    /**
     * Stages the difference between the live cycle and the submitted form state. Returns null when nothing differs.
     *
     * @param  array<string,mixed>  $state  form state (scalars and relation id lists)
     */
    public function propose(ScholarshipCycle $cycle, User $by, array $state): ?CycleRevision
    {
        if (! $by->hasPermission('content.draft')) {
            throw new AuthorizationException('You cannot propose changes.');
        }
        $live = $this->snapshot($cycle);
        $changes = ['fields' => [], 'relations' => []];

        foreach (self::FIELDS as $f) {
            if (! array_key_exists($f, $state)) {
                continue;
            }
            $new = $state[$f];
            $new = in_array($f, ['fully_funded_verified', 'any_nationality'], true) ? (bool) $new : ($new === '' ? null : $new);
            if ($f === 'fully_funded_verified' && ! $by->hasPermission('content.review')) {
                continue; // only reviewers may assert verification
            }
            if ($this->normalise($new) !== $this->normalise($live['fields'][$f])) {
                $changes['fields'][$f] = $new;
            }
        }
        foreach (self::RELATIONS as $r) {
            if (! array_key_exists($r, $state)) {
                continue;
            }
            $new = collect($state[$r] ?? [])->map(fn ($i) => (int) $i)->sort()->values()->all();
            if ($new !== $live['relations'][$r]) {
                $changes['relations'][$r] = $new;
            }
        }
        if ($changes['fields'] === [] && $changes['relations'] === []) {
            return null;
        }

        return DB::transaction(function () use ($cycle, $by, $changes) {
            // A person has one open proposal per cycle; a new one replaces their earlier one.
            CycleRevision::where('cycle_id', $cycle->id)->where('status', 'pending')->where('proposed_by', $by->id)->update(['status' => 'withdrawn']);
            $revision = CycleRevision::create(['cycle_id' => $cycle->id, 'proposed_by' => $by->id, 'changes' => $changes, 'base_hash' => $this->hash($cycle)]);
            AuditLog::record('cycle.revision_proposed', $cycle, null, ['fields' => array_keys($changes['fields']), 'relations' => array_keys($changes['relations'])], null, $by);

            return $revision;
        });
    }

    /** @return list<array{label:string,current:string,proposed:string}> */
    public function diff(CycleRevision $revision): array
    {
        $cycle = $revision->cycle;
        $live = $this->snapshot($cycle);
        $rows = [];
        $show = fn ($v) => is_bool($v) ? ($v ? 'Yes' : 'No') : (($v === null || $v === '') ? '(empty)' : (string) $v);

        foreach ($revision->changes['fields'] ?? [] as $f => $new) {
            $rows[] = ['label' => ucfirst(str_replace('_', ' ', $f)), 'current' => $show($live['fields'][$f]), 'proposed' => $show($new)];
        }
        foreach ($revision->changes['relations'] ?? [] as $r => $ids) {
            $related = $cycle->{$r}()->getRelated();
            $names = fn (array $list) => $list ? $related->newQuery()->whereIn('id', $list)->get()->map(fn ($m) => $m->name ?? $m->title ?? $m->id)->sort()->implode(', ') : '(none)';
            $rows[] = ['label' => ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $r)), 'current' => $names($live['relations'][$r]), 'proposed' => $names($ids)];
        }

        return $rows;
    }

    public function apply(CycleRevision $revision, User $reviewer, ?string $notes = null, ?string $overrideReason = null): ScholarshipCycle
    {
        $this->assertPending($revision);
        $override = false;
        if ($revision->proposed_by === $reviewer->id) {
            if (! $reviewer->hasPermission('content.override') || blank($overrideReason)) {
                throw new AuthorizationException('You cannot approve your own change.');
            }
            $override = true;
            $notes = trim('Override: '.$overrideReason.' '.$notes);
        } elseif (! $reviewer->hasPermission('content.review')) {
            throw new AuthorizationException('Missing permission content.review.');
        }

        $cycle = $revision->cycle;
        if ($this->hash($cycle) !== $revision->base_hash) {
            $revision->update(['status' => 'outdated', 'decided_by' => $reviewer->id, 'decided_at' => now(), 'decision_notes' => 'The live listing changed after this proposal was made.']);
            throw ValidationException::withMessages(['revision' => 'The live listing changed after this proposal was made. Ask the author to propose it again.']);
        }

        return DB::transaction(function () use ($revision, $cycle, $reviewer, $notes, $override) {
            $before = $this->snapshot($cycle);
            $cycle->forceFill($revision->changes['fields'] ?? [])->save();
            foreach ($revision->changes['relations'] ?? [] as $r => $ids) {
                $cycle->{$r}()->sync($ids);
            }
            $revision->update(['status' => 'applied', 'decided_by' => $reviewer->id, 'decided_at' => now(), 'decision_notes' => $notes, 'override' => $override]);
            CycleReview::create(['cycle_id' => $cycle->id, 'reviewer_id' => $reviewer->id, 'from_state' => $cycle->publication_state->value,
                'to_state' => $cycle->publication_state->value, 'notes' => 'Revision applied. '.$notes, 'override' => $override]);
            AuditLog::record('cycle.revision_applied', $cycle, ['fields' => array_intersect_key($before['fields'], $revision->changes['fields'] ?? [])],
                ['fields' => $revision->changes['fields'] ?? [], 'relations' => $revision->changes['relations'] ?? []], $notes, $reviewer);

            return $cycle->fresh();
        });
    }

    public function reject(CycleRevision $revision, User $reviewer, string $notes): void
    {
        $this->assertPending($revision);
        if (! $reviewer->hasPermission('content.review') && $revision->proposed_by !== $reviewer->id) {
            throw new AuthorizationException('Missing permission content.review.');
        }
        if (trim($notes) === '') {
            throw ValidationException::withMessages(['notes' => 'Explain why.']);
        }
        $revision->update(['status' => $revision->proposed_by === $reviewer->id ? 'withdrawn' : 'rejected', 'decided_by' => $reviewer->id, 'decided_at' => now(), 'decision_notes' => $notes]);
        AuditLog::record('cycle.revision_rejected', $revision->cycle, null, null, $notes, $reviewer);
    }

    private function assertPending(CycleRevision $revision): void
    {
        if ($revision->status !== 'pending') {
            throw ValidationException::withMessages(['revision' => 'This proposal is no longer pending.']);
        }
    }

    private function normalise(mixed $v): mixed
    {
        return is_numeric($v) && ! is_bool($v) ? (string) $v : ($v === '' ? null : $v);
    }
}
