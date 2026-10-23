<x-layouts.partner title="Application list">
    <h1 class="text-2xl font-extrabold">Application list</h1>

    <form method="GET" action="{{ route('partner.applications.index') }}" class="mt-4 flex flex-wrap items-end gap-3" role="search">
        <div class="min-w-[14rem] flex-1"><label for="q" class="label">Search by student, email, scholarship or application number</label><input id="q" name="q" type="search" value="{{ $filters['q'] }}" class="input" maxlength="80"></div>
        <div><label for="stage" class="label">Status</label>
            <select id="stage" name="stage" class="input"><option value="">All</option>@foreach (\App\Models\ApplicationPlan::STAGES as $k => $l)<option value="{{ $k }}" @selected($filters['stage'] === $k)>{{ $l }}</option>@endforeach</select></div>
        <details class="w-full text-sm" @if ($filters['from'] || $filters['to']) open @endif>
            <summary class="cursor-pointer text-navy-700 underline">Advanced search</summary>
            <div class="mt-2 flex flex-wrap gap-3">
                <div><label for="from" class="label">Last changed from</label><input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="input"></div>
                <div><label for="to" class="label">to</label><input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="input"></div>
            </div>
        </details>
        <button class="btn-primary">Search</button>
    </form>

    <p class="mt-4 text-sm text-muted" aria-live="polite">{{ $plans->total() }} {{ \Illuminate\Support\Str::plural('application', $plans->total()) }}</p>
    <div class="card mt-2 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Applications of your students</caption>
            <thead><tr class="border-b border-line text-muted">
                @foreach (['Application number', 'Scholarship', 'Name', 'Gender', 'Country', 'Last operation time', 'Status', 'Review notes', 'Application fee', 'Agency charge', 'Operate'] as $h)<th scope="col" class="px-3 py-3">{{ $h }}</th>@endforeach
            </tr></thead>
            <tbody>
            @forelse ($plans as $p)
                @php($data = $forms->formFor($p->user)->data ?? [])
                @php($case = ($cases[$p->user_id.'-'.$p->cycle_id] ?? collect())->first())
                @php($fee = fn ($kind) => $case?->invoices->where('kind', $kind)->where('status', '!=', 'void')->last()?->feeLabel() ?? '-')
                <tr class="border-b border-line/60 align-top">
                    <td class="px-3 py-3 whitespace-nowrap">{{ $p->application_no }}</td>
                    <td class="px-3 py-3">{{ $p->cycle->displayName() }}</td>
                    <td class="px-3 py-3">{{ $p->user->name }}</td>
                    <td class="px-3 py-3">{{ $data['gender'] ?? '-' }}</td>
                    <td class="px-3 py-3">{{ $data['nationality'] ?? '-' }}</td>
                    <td class="px-3 py-3 whitespace-nowrap">{{ $p->updated_at->format('Y-m-d H:i') }}</td>
                    <td class="px-3 py-3"><span class="{{ match ($p->stage) { 'passed' => 'badge-green', 'returned' => 'badge-alert', 'submitted' => 'badge-warn', default => 'badge-neutral' } }}">{{ $p->statusFor($case) }}</span></td>
                    <td class="px-3 py-3 max-w-xs whitespace-pre-line">{{ $p->review_remarks }}</td>
                    <td class="px-3 py-3">{{ $fee('application_fee') }}</td>
                    <td class="px-3 py-3">{{ $fee('agency_charge') }}</td>
                    <td class="px-3 py-3 whitespace-nowrap"><a class="text-navy-700 underline" href="{{ route('partner.student.applications.show', [$p->user->public_id, $p->public_id]) }}">{{ $p->isEditable() ? 'Continue' : 'View details' }}</a><br><a class="text-navy-700 underline" href="{{ route('partner.student.applications.preview', [$p->user->public_id, $p->public_id]) }}" target="_blank" rel="noopener">Download application form</a></td>
                </tr>
            @empty
                <tr><td colspan="11" class="px-4 py-8 text-center text-muted">No applications match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $plans->links('pagination::tailwind') }}</div>
</x-layouts.partner>
