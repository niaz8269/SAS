<x-layouts.public title="Enquiry {{ $enquiry->reference }}" :noindex="true">
    <div class="container-page max-w-3xl py-10">
        <h1 class="text-2xl font-extrabold">Enquiry {{ $enquiry->reference }}</h1>
        <p class="mt-1 text-sm text-muted">Status: <span class="badge-neutral">{{ $enquiry->status->label() }}</span> · Sent {{ $enquiry->created_at->format('j M Y, H:i') }} UTC@if($enquiry->service) · {{ $enquiry->service->name }}@endif</p>

        <div class="card mt-6 p-5"><h2 class="text-sm font-semibold text-muted">Your message</h2><p class="mt-2 whitespace-pre-line">{{ $enquiry->message }}</p></div>

        <section class="mt-6 space-y-3" aria-labelledby="thread"><h2 id="thread" class="text-lg font-bold">Conversation</h2>
            @forelse ($enquiry->publicMessages as $m)
                <div class="card p-4 {{ $m->author_id && $m->author?->isStaff() ? 'border-forest-300 bg-forest-50' : '' }}">
                    <p class="text-xs text-muted">{{ $m->author?->isStaff() ? 'SAS team' : 'You' }} · {{ $m->created_at->format('j M Y, H:i') }} UTC</p>
                    <p class="mt-1 whitespace-pre-line">{{ $m->body }}</p>
                </div>
            @empty
                <p class="text-sm text-muted">No replies yet. We will respond by {{ $enquiry->contact_preference }}.</p>
            @endforelse
        </section>

        @if ($enquiry->status !== \App\Enums\EnquiryStatus::Closed)
            <form method="POST" action="{{ route('enquiries.reply', $enquiry) }}" class="card mt-6 space-y-3 p-5">
                @csrf
                @if ($token)<input type="hidden" name="token" value="{{ $token }}">@endif
                <label for="body" class="label">Add a message</label>
                <textarea id="body" name="body" rows="4" required class="input @error('body') input-error @enderror"></textarea>
                @error('body')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                <button class="btn-primary">Send message</button>
            </form>
        @endif
    </div>
</x-layouts.public>
