<x-layouts.partner :title="$student->name" :crumb="'Student list › '.$student->name">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><p class="text-[13px]">Backend home</p><h1 class="text-[24px] font-light uppercase leading-tight text-[#3a87ad]">{{ $student->name }}</h1>
            <p class="mt-1 text-[13px] text-[#555]">{{ $student->email }} &middot; {{ $student->email_verified_at ? 'Account active' : 'Invitation pending' }} &middot; details {{ $formPercent }}% filled in</p></div>
        <a class="inline-flex items-center gap-2 bg-[#d9534f] px-5 py-2.5 text-[14px] text-white hover:bg-[#c9453f]" href="{{ route('partner.students.index') }}"><span aria-hidden="true">↩</span> return</a>
    </div>
    <div class="mt-2 border-b border-[#e5e5e5]"></div>

    <div class="mt-5 flex flex-wrap gap-2 text-[14px]">
        <a class="bg-[#307cc7] px-5 py-2.5 text-white hover:bg-[#2a6fb4]" href="{{ route('partner.student.choose.index', $student->public_id) }}">+ Add application</a>
        <a class="bg-[#86b87c] px-5 py-2.5 text-white hover:bg-[#78aa6e]" href="{{ route('partner.student.form.show', $student->public_id) }}">Application form</a>
        <a class="bg-[#a5adb3] px-5 py-2.5 text-white hover:bg-[#939ba1]" href="{{ route('partner.student.edit', $student->public_id) }}">Edit</a>
    </div>

    @unless ($student->email_verified_at)
        <form method="POST" action="{{ route('partner.student.invite', $student->public_id) }}" class="mt-5 flex flex-wrap items-center gap-3 border border-[#e5e5e5] bg-[#f9f9f9] p-4 text-[13px]">@csrf
            <p class="flex-1">The student has not chosen a password yet.</p><button class="border border-[#ccc] bg-white px-4 py-2 hover:bg-[#f0f6fa]">Send the invitation again</button></form>
    @endunless

    <x-agent.list title="Applications" class="max-w-4xl">
        <table class="w-full text-left text-[13px]">
            <thead><tr><x-agent.th name="no" :sortable="false">Application number</x-agent.th><x-agent.th name="sch" :sortable="false">Scholarship</x-agent.th><x-agent.th name="st" :sortable="false">Status</x-agent.th><x-agent.th name="op" :sortable="false"><span class="text-[#428bb6]">operate</span></x-agent.th></tr></thead>
            <tbody>
            @forelse ($plans as $p)
                <tr class="border-b border-[#e5e5e5]"><td class="px-3 py-4">{{ $p->application_no }}</td><td class="px-3 py-4">{{ $p->cycle->displayName() }}</td><td class="px-3 py-4">{{ $p->stageLabel() }}</td>
                    <td class="px-3 py-4"><a class="text-[#428bb6] hover:underline" href="{{ route('partner.student.applications.show', [$student->public_id, $p->public_id]) }}">{{ $p->isEditable() ? 'Continue' : 'View Details' }}</a></td></tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-[#777]">No applications yet. Press + Add application to choose a scholarship.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-agent.list>
</x-layouts.partner>
