<x-layouts.public title="Enquiry received" :noindex="true">
    <div class="container-page max-w-2xl py-12">
        <div class="card p-8" role="status">
            <h1 class="text-2xl font-extrabold">Thank you. We received your enquiry.</h1>
            <p class="mt-3">Your reference number is <strong class="rounded bg-lime-soft px-2 py-0.5">{{ $enquiry['reference'] }}</strong>. Please quote it if you contact us.</p>
            <p class="mt-3 text-sm text-muted">A confirmation email is on its way. If it does not arrive, you can still follow your enquiry with this private link (keep it to yourself; it expires in 30 days):</p>
            <p class="mt-2 break-all text-sm"><a class="text-forest-700 underline" href="{{ $enquiry['link'] }}">{{ $enquiry['link'] }}</a></p>
            <a href="{{ route('home') }}" class="btn-outline mt-6">Back to the homepage</a>
        </div>
    </div>
</x-layouts.public>
