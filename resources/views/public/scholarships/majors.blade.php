@php
    $title = $programme->title.': programmes and majors';
    $cycleUrl = route('scholarships.show', [$programme->slug, $cycle->slug]);
    $total = $levels->sum('count');
    $noticeTotal = $rows->total();
    $crumbs = [['Home', route('home')], ['Scholarships', route('scholarships.index')], [$programme->title, $cycleUrl], ['Programmes and majors']];
@endphp
<x-layouts.public :title="\Illuminate\Support\Str::limit($title, 58, '…', true)" :description="'All '.$total.' programmes and majors listed for '.$programme->title.' '.$cycle->intake_year.', by degree level, from its official sources. Search by your major.'" :crumbs="$crumbs">
    <section class="container-page py-8">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('scholarships.index') }}">Scholarships</a> / <a class="hover:underline" href="{{ $cycleUrl }}">{{ $programme->title }}</a> / <span aria-current="page">Programmes and majors</span></nav>
        <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">{{ $programme->title }}: programmes and majors</h1>
        <p class="mt-2 max-w-3xl text-lg text-muted">{{ number_format($total) }} programmes and majors are listed for this scholarship{{ $catalogue ? ', at universities across China,' : '' }} as its official sources {{ $catalogue ? 'and the Study in China system ' : '' }}name them. Find yours below, then open the scholarship for the documents, funding and application process.</p>

        <form method="GET" action="{{ route('scholarships.majors', [$programme->slug, $cycle->slug]) }}" class="card mt-5 grid gap-3 p-4 md:grid-cols-[1fr_14rem_auto] md:items-end" role="search">
            <div><label for="m-q" class="label">Your major or subject</label><input id="m-q" name="q" type="search" value="{{ $q }}" class="input" placeholder="e.g. civil engineering, nursing" maxlength="80"></div>
            <div><label for="m-level" class="label">Degree level</label>
                <select id="m-level" name="level" class="input"><option value="">All levels ({{ number_format($total) }})</option>@foreach ($levels as $slug => $l)<option value="{{ $slug }}" @selected($level === $slug)>{{ $l['name'] }} ({{ number_format($l['count']) }})</option>@endforeach</select></div>
            <button class="btn-primary">Search</button>
        </form>

        @if ($catalogue)
            <section class="mt-6" aria-labelledby="csc-catalogue">
                <h2 id="csc-catalogue" class="text-xl font-bold">Programmes open to Chinese Government Scholarship applicants</h2>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-muted">{{ number_format($catalogueTotal) }} programmes at universities in China that the Study in China system lists as open to Chinese Government Scholarship (CGS) applicants. Which route applies (Type A, Type B or Silk Road) depends on the university and on your country: check the call page and the university's page before you choose.</p>
                <p class="mt-3 text-sm text-muted" aria-live="polite">{{ number_format($catalogue->total()) }} {{ \Illuminate\Support\Str::plural('match', $catalogue->total()) }}{{ $q !== '' ? ' for “'.$q.'”' : '' }}{{ $level !== '' ? ' at this level' : '' }}.</p>
                @if ($catalogue->isEmpty())
                    <p class="card mt-3 p-6 text-sm text-muted">No programme in the Study in China list matches that word. Try a broader word or another level.</p>
                @else
                    @include('public.programmes._table', ['programmes' => $catalogue, 'showUniversity' => true])
                    <div class="mt-6">{{ $catalogue->links('pagination::tailwind') }}</div>
                @endif
            </section>
        @endif

        @if ($noticeTotal > 0 || ! $catalogue)
            <h2 class="mt-10 text-xl font-bold">{{ $catalogue ? 'Majors named in the notices and guides of these calls' : 'Programmes and majors' }}</h2>
        @endif
        @if ($noticeTotal > 0 || ! $catalogue)<p class="mt-4 text-sm text-muted" aria-live="polite">{{ number_format($rows->total()) }} {{ \Illuminate\Support\Str::plural('match', $rows->total()) }}{{ $q !== '' ? ' for “'.$q.'”' : '' }}.</p>
        @if ($rows->isEmpty())
            <p class="card mt-4 p-6 text-sm text-muted">No listed programme matches that word. The list is what the official sources name; try a broader word, or open the <a class="text-navy-700 underline" href="{{ $cycleUrl }}">scholarship page</a> to see whether the subject is open to any field.</p>
        @else
            <div class="card mt-4 overflow-x-auto p-0"><table class="w-full text-left text-sm">
                <caption class="sr-only">Programmes and majors of {{ $programme->title }}</caption>
                <thead><tr class="border-b border-line text-muted"><th scope="col" class="px-4 py-2">Programme or major</th><th scope="col" class="px-4 py-2">Level</th><th scope="col" class="px-4 py-2">University or institution</th><th scope="col" class="px-4 py-2">Details</th></tr></thead>
                <tbody>@foreach ($rows as $o)
                    <tr class="border-b border-line/60 align-top"><td class="px-4 py-2 font-medium">{{ $o->name }}</td><td class="px-4 py-2">{{ $o->degreeLevel->name }}</td><td class="px-4 py-2">{{ $o->institution ?: '—' }}</td><td class="px-4 py-2 text-muted">{{ $o->detail ?: '—' }}</td></tr>
                @endforeach</tbody></table></div>
            <div class="mt-6">{{ $rows->links('pagination::tailwind') }}</div>
        @endif
        @endif
        <p class="mt-6 text-sm"><a class="font-semibold text-navy-700 underline" href="{{ $cycleUrl }}">&larr; Back to {{ $programme->title }}: requirements, funding and how to apply</a></p>
    </section>
</x-layouts.public>
