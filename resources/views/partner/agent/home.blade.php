<x-layouts.partner title="Backend home" crumb="Backend home">
    <x-agent.title>Backend home</x-agent.title>

    <div class="mt-8 flex flex-wrap items-start gap-10">
        <div class="w-full max-w-[360px]">
            <div class="mx-auto flex h-[110px] w-[165px] items-center justify-center border border-[#ddd] bg-white p-2 shadow-sm"><x-brand class="[&_img]:!h-16 [&_img]:!max-w-[8rem]" /></div>
            <p class="mt-3 truncate text-center text-sm text-[#666]">{{ $organisation->name }}</p>
            <a href="{{ route('partner.organisation.edit') }}" class="mt-4 flex h-14 items-center justify-center gap-2 bg-[#86b87c] text-[19px] text-white hover:bg-[#78aa6e]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4ZM14 6l4 4"/></svg>Edit data</a>
            <a href="{{ route('partner.password.edit') }}" class="mt-2 flex h-14 items-center justify-center gap-2 bg-[#3d88c9] text-[19px] text-white hover:bg-[#3479b6]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 11h12v10H6V11Zm3 0V8a3 3 0 0 1 6 0v3"/></svg>Change password</a>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-4 sm:max-w-[420px]">
            <a href="{{ route('partner.applications.index') }}" class="flex h-[190px] items-center gap-6 bg-[#9abf32] px-7 text-white shadow transition hover:brightness-95" aria-label="{{ $applications }} applications">
                <span class="inline-flex h-24 w-24 shrink-0 items-center justify-center rounded-full bg-white/25" aria-hidden="true"><svg class="h-10 w-10" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="10" width="5" height="5" rx="1"/><rect x="9.5" y="10" width="5" height="5" rx="1"/><rect x="16" y="10" width="5" height="5" rx="1"/></svg></span>
                <span><span class="block text-5xl font-light">{{ $applications }}</span><span class="block text-2xl leading-tight">Number of applications</span></span>
            </a>
            <a href="{{ route('partner.students.index') }}" class="flex h-[190px] items-center gap-6 bg-[#6ab0de] px-7 text-white shadow transition hover:brightness-95" aria-label="{{ $students }} students">
                <span class="inline-flex h-24 w-24 shrink-0 items-center justify-center rounded-full bg-white/25" aria-hidden="true"><svg class="h-11 w-11" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3h4v4h-4V3Zm0 7h4v11h-4V10Z"/></svg></span>
                <span><span class="block text-5xl font-light">{{ $students }}</span><span class="block text-2xl leading-tight">Number of students</span></span>
            </a>
        </div>
    </div>
</x-layouts.partner>
