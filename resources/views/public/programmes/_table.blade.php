@props(['programmes', 'showUniversity' => true])
<div class="card mt-4 overflow-x-auto">
    <table class="w-full min-w-[44rem] text-left text-sm">
        <caption class="sr-only">Degree programmes</caption>
        <thead class="border-b border-line bg-navy-50/60 text-xs uppercase tracking-wide text-muted">
            <tr>
                <th scope="col" class="px-4 py-3">Programme</th>
                @if ($showUniversity)<th scope="col" class="px-4 py-3">University</th>@endif
                <th scope="col" class="px-4 py-3">Level</th>
                <th scope="col" class="px-4 py-3">Language</th>
                <th scope="col" class="px-4 py-3">Years</th>
                <th scope="col" class="px-4 py-3">Tuition (CNY / year)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @foreach ($programmes as $p)
                <tr>
                    <td class="px-4 py-3">
                        <span class="font-semibold">{{ $p->displayName() }}</span>
                        @if ($p->csc_eligible)<span class="badge-gold ml-1" title="Listed as open to Chinese Government Scholarship applicants on the official system">&#9733; CSC listed</span>@endif
                        @if ($p->name_en)<span class="block text-xs text-muted" lang="zh-Hans">{{ $p->name_zh }}</span>@endif
                    </td>
                    @if ($showUniversity)
                        <td class="px-4 py-3"><a class="text-navy-700 underline" href="{{ route('universities.show', $p->university) }}">{{ $p->university->name }}</a><span class="block text-xs text-muted">{{ collect([$p->university->city, $p->university->province])->filter()->unique()->implode(', ') }}</span></td>
                    @endif
                    <td class="px-4 py-3">{{ $p->levelLabel() }}</td>
                    <td class="px-4 py-3">{{ $p->languageLabel() }}</td>
                    <td class="px-4 py-3">{{ $p->duration_years !== null ? rtrim(rtrim(number_format($p->duration_years, 1), '0'), '.') : 'Not listed' }}</td>
                    <td class="px-4 py-3">{{ $p->tuition_cny ? number_format($p->tuition_cny) : 'Not listed' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>