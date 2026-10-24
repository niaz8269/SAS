<x-layouts.student title="My counsellor">
    <h1 class="text-2xl font-extrabold">Your counsellor</h1>
    <p class="mt-1 text-sm text-muted">
        @if ($counsellor) <strong>{{ $counsellor->name }}</strong> looks after your applications at SAS. @else A counsellor has not been assigned to you yet. You can still write below: the SAS team will read your message and reply here. @endif
        To help you, your counsellor can read your details, application form and documents. Nobody else at SAS sees them unless it is part of their job.
    </p>

    <section class="card mt-5 p-4" aria-labelledby="thread">
        <h2 id="thread" class="text-lg font-bold">Messages</h2>
        <ul class="mt-3 space-y-3">
            @forelse ($messages as $m)
                <li class="max-w-[85%] rounded-lg border p-3 text-sm {{ $m->fromStudent() ? 'ml-auto border-navy-300 bg-navy-50' : 'border-line bg-white' }}">
                    <p class="text-xs text-muted">{{ $m->fromStudent() ? 'You' : ($m->author?->name ?: 'SAS team') }} &middot; {{ $m->created_at->format('j M Y, H:i') }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $m->body }}</p>
                </li>
            @empty
                <li class="text-sm text-muted">No messages yet. Tell your counsellor what you want to study and where.</li>
            @endforelse
        </ul>
        <form method="POST" action="{{ route('student.counsellor.store') }}" class="mt-4 space-y-2" id="end">
            @csrf
            <label for="body" class="label">Write a message</label>
            <textarea id="body" name="body" rows="4" maxlength="2000" required class="input @error('body') border-alert @enderror">{{ old('body') }}</textarea>
            @error('body')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            <button class="btn-primary">Send</button>
        </form>
    </section>
</x-layouts.student>