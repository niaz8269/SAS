<x-layouts.public title="Our services" description="Advisory, writing, document evaluation, translation, attestation, admissions and visa support for international students.">
    <section class="container-page py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">Support beyond the scholarship</h1>
        <p class="mt-2 max-w-3xl text-muted">Professional help at each step. Every service starts with a free enquiry; we confirm scope, deliverables and any fee with you before work begins. No service guarantees scholarship selection, admission, attestation acceptance or a visa decision.</p>
        @if ($services->isEmpty())
            <p class="card mt-6 p-6 text-sm text-muted">Service pages will appear here once published. You can still <a class="text-forest-700 underline" href="{{ route('contact') }}">send us an enquiry</a>.</p>
        @else
            <ul class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $s)
                    <li class="card flex flex-col p-6"><p class="eyebrow">{{ ucfirst(str_replace('_', ' ', $s->category)) }}</p>
                        <h2 class="mt-1 text-lg font-bold"><a class="hover:underline" href="{{ route('services.show', $s) }}">{{ $s->name }}</a></h2>
                        <p class="mt-2 text-sm text-muted">{{ $s->summary }}</p>
                        <p class="mt-3 text-xs text-muted">{{ $s->priceLabel() }}</p>
                        <a href="{{ route('services.show', $s) }}" class="btn-outline mt-auto self-start">Learn more</a></li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.public>
