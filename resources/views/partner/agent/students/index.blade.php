<x-layouts.partner title="Student list" crumb="Student list">
    <x-agent.title>Student list
        <x-slot name="actions"><button type="button" class="inline-flex items-center gap-2 bg-[#d9534f] px-5 py-2.5 text-[17px] text-white hover:bg-[#c9453f]" onclick="document.getElementById('advanced').toggleAttribute('hidden')" aria-controls="advanced"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 5 5"/></svg>Advanced Search</button></x-slot>
    </x-agent.title>

    <form id="advanced" method="GET" action="{{ route('partner.students.index') }}" class="mt-4 flex flex-wrap items-end gap-3 border border-[#ddd] bg-[#f9f9f9] p-4" @if (! $q) hidden @endif>
        <div><label for="adv-q" class="mb-1 block text-sm font-semibold">Name or email</label><input id="adv-q" name="q" type="search" value="{{ $q }}" maxlength="80" class="w-64 max-w-full border border-[#ccc] bg-white px-3 py-2"></div>
        <button class="bg-[#307cc7] px-5 py-2 text-white">Search</button>
        <a class="border border-[#ccc] bg-white px-5 py-2" href="{{ route('partner.students.index') }}">Reset</a>
    </form>

    <x-agent.list title="List">
        <x-slot name="actions">
            <a class="inline-flex items-center gap-2 hover:underline" href="{{ route('partner.students.create') }}"><span class="text-2xl leading-none" aria-hidden="true">+</span> Add students</a>
            <a class="inline-flex items-center gap-2 hover:underline" href="{{ route('partner.students.import') }}"><span class="text-2xl leading-none" aria-hidden="true">+</span> Batch import</a>
        </x-slot>
        <x-agent.pager :paginator="$students" search-label="Name/Email" :q="$q" :per-page="$perPage" />
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[17px]">
                <caption class="sr-only">Your students</caption>
                <thead><tr>
                    <x-agent.th name="id" :sort="$sort" :dir="$dir">ID</x-agent.th>
                    <x-agent.th name="name" :sort="$sort" :dir="$dir">English name</x-agent.th>
                    <x-agent.th name="email" :sort="$sort" :dir="$dir">Mail</x-agent.th>
                    <x-agent.th name="passport" :sortable="false">Passport number</x-agent.th>
                    <x-agent.th name="dob" :sortable="false">Date of birth</x-agent.th>
                    <x-agent.th name="country" :sortable="false">Country</x-agent.th>
                    <x-agent.th name="actions" :sortable="false"><span class="sr-only">Actions</span></x-agent.th>
                </tr></thead>
                <tbody>
                @forelse ($students as $s)
                    @php($d = $s->applicationForm?->data ?? [])
                    <tr class="border-b border-[#e5e5e5] align-top">
                        <td class="px-3 py-5">{{ $s->id }}</td>
                        <td class="px-3 py-5 uppercase"><a class="hover:text-[#428bb6]" href="{{ route('partner.student.show', $s->public_id) }}">{{ $s->name }}</a></td>
                        <td class="px-3 py-5"><a class="text-[#428bb6] hover:underline" href="mailto:{{ $s->email }}">{{ $s->email }}</a><br><span class="text-sm text-[#777]">{{ $s->email_verified_at ? 'Account active' : 'Invitation pending' }}</span></td>
                        <td class="px-3 py-5">{{ $d['passport_no'] ?? '' }}</td>
                        <td class="px-3 py-5">@if (! empty($d['date_of_birth'])){{ $d['date_of_birth'] }} ({{ \Illuminate\Support\Carbon::parse($d['date_of_birth'])->age }} years old)@endif</td>
                        <td class="px-3 py-5">{{ $d['nationality'] ?? '' }}</td>
                        <td class="whitespace-nowrap px-3 py-5"><a class="text-[#428bb6] hover:underline" href="{{ route('partner.student.edit', $s->public_id) }}">Edit</a> | <a class="text-[#428bb6] hover:underline" href="{{ route('partner.student.choose.index', $s->public_id) }}">Add Application</a>
                            <br><a class="text-sm text-[#428bb6] hover:underline" href="{{ route('partner.student.applications.index', $s->public_id) }}">{{ $s->application_plans_count }} {{ \Illuminate\Support\Str::plural('application', $s->application_plans_count) }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-[#777]">No students yet. Press + Add students to start.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pb-2"></div>
    </x-agent.list>
</x-layouts.partner>
