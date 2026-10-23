<x-layouts.partner title="Backend home" crumb="Backend home">
    <x-agent.title>Backend home</x-agent.title>

    <div class="mt-6 flex flex-wrap items-start gap-8">
        <div class="w-full max-w-[300px]">
            <div class="flex justify-center"><x-brand class="[&_img]:!h-24 [&_img]:!max-w-[16rem]" /></div>
            <p class="mt-3 truncate text-center text-sm text-[#666]">{{ $organisation->name }}</p>
            <a href="{{ route('partner.organisation.edit') }}" class="mt-4 flex h-11 items-center justify-center gap-2 bg-[#86b87c] text-[13px] text-white hover:bg-[#78aa6e]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4ZM14 6l4 4"/></svg>Edit data</a>
            <a href="{{ route('partner.password.edit') }}" class="mt-2 flex h-11 items-center justify-center gap-2 bg-[#3d88c9] text-[13px] text-white hover:bg-[#3479b6]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 11h12v10H6V11Zm3 0V8a3 3 0 0 1 6 0v3"/></svg>Change password</a>
        </div>

        <div class="flex w-full max-w-[300px] flex-col gap-3">
            <a href="{{ route('partner.applications.index') }}" class="flex h-[100px] items-center gap-4 bg-[#9abf32] px-5 text-white transition hover:brightness-95" aria-label="{{ $applications }} applications">
                <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/25" aria-hidden="true"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="10" width="5" height="5" rx="1"/><rect x="9.5" y="10" width="5" height="5" rx="1"/><rect x="16" y="10" width="5" height="5" rx="1"/></svg></span>
                <span><span class="block text-3xl font-light leading-none">{{ $applications }}</span><span class="mt-1 block text-[13px] leading-tight">Number of applications</span></span>
            </a>
            <a href="{{ route('partner.students.index') }}" class="flex h-[100px] items-center gap-4 bg-[#6ab0de] px-5 text-white transition hover:brightness-95" aria-label="{{ $students }} students">
                <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/25" aria-hidden="true"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3h4v4h-4V3Zm0 7h4v11h-4V10Z"/></svg></span>
                <span><span class="block text-3xl font-light leading-none">{{ $students }}</span><span class="mt-1 block text-[13px] leading-tight">Number of students</span></span>
            </a>
        </div>
    </div>
</x-layouts.partner>
