<x-dynamic-component :component="$ctx->layout()" title="Journey">
    @include('student.wizard._header')

    <div class="mx-auto mt-6 max-w-4xl">
        <h2 class="border-b border-[#cfd8e3] pb-2 text-[18px] text-[#0b6ec7]">Your journey</h2>
        <p class="mt-2 text-[13px] text-[#666]">Where this application is now, and what you need to do next. SAS moves it along as things happen.</p>

        <ol class="mt-5 space-y-3">
            @php($currentIndex = $journey->index($current))
            @foreach ($stages as $key => $stage)
                @continue($key === 'closed' && $current !== 'closed')
                @php($i = $journey->index($key))
                @php($state = $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'now' : 'next'))
                <li id="stage-{{ $key }}" class="border {{ $state === 'now' ? 'border-[#0b4a8f]' : 'border-[#ddd]' }} bg-white">
                    <div class="flex items-center gap-3 px-4 py-3 {{ $state === 'now' ? 'bg-[#e8f1fb]' : 'bg-[#f5f5f5]' }}">
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[13px] font-bold {{ $state === 'done' ? 'bg-[#2f8f4e] text-white' : ($state === 'now' ? 'bg-[#0b4a8f] text-white' : 'bg-[#ccc] text-white') }}" aria-hidden="true">{{ $state === 'done' ? '✓' : $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[15px] font-semibold {{ $state === 'next' ? 'text-[#777]' : 'text-[#222]' }}">{{ $stage['label'] }} @if ($state === 'now')<span class="ml-2 bg-[#0b4a8f] px-2 py-0.5 text-[11px] font-semibold text-white">You are here</span>@endif</p>
                            <p class="text-[12px] text-[#666]">{{ $stage['hint'] }}</p>
                        </div>
                    </div>
                    @if ($tasks[$key]->isNotEmpty())
                        <ul class="divide-y divide-[#eee] text-[14px]">
                            @foreach ($tasks[$key] as $task)
                                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                                    <form method="POST" action="{{ $ctx->route('applications.journey.tasks.update', [$plan, $task]) }}" class="flex items-center gap-3">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="done" value="{{ $task->isDone() ? 0 : 1 }}">
                                        <button class="inline-flex h-6 w-6 items-center justify-center border {{ $task->isDone() ? 'border-[#2f8f4e] bg-[#2f8f4e] text-white' : 'border-[#999] bg-white' }}" aria-pressed="{{ $task->isDone() ? 'true' : 'false' }}" aria-label="{{ $task->isDone() ? 'Mark not done' : 'Mark done' }}: {{ $task->title }}">@if ($task->isDone())✓@endif</button>
                                        <span class="{{ $task->isDone() ? 'text-[#999] line-through' : '' }}">{{ $task->title }}</span>
                                    </form>
                                    @if ($task->due_on)<span class="text-[12px] {{ $task->isOverdue() ? 'font-semibold text-[#d9534f]' : 'text-[#666]' }}">{{ $task->isOverdue() ? 'Overdue: ' : 'Due ' }}{{ $task->due_on->format('j M Y') }}</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</x-dynamic-component>