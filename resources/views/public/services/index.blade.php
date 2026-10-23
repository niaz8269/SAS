@php $intro = \App\Models\PageBlock::builtIn('services', 'intro'); $emptyMsg = \App\Models\PageBlock::builtIn('services', 'empty'); @endphp
<x-layouts.public title="Our services" :description="$intro?->subheading ?: 'Advisory, writing, document evaluation, translation, attestation, admissions and visa support for international students.'">
    <x-page-blocks page="services" position="top" />
    <section class="container-page py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $intro?->heading ?: 'Support beyond the scholarship' }}</h1>
        <p class="mt-2 max-w-3xl text-muted">{{ $intro?->body ?: 'Professional help at each step. Every service starts with a free enquiry; we confirm scope, deliverables and any fee with you before work begins. No service guarantees scholarship selection, admission, attestation acceptance or a visa decision.' }}</p>
        <div class="mt-6 max-w-5xl">
            <h2 class="text-xl font-bold">Our fees</h2>
            @include('public.legal.fees')
            <p class="text-sm text-muted">Details of when each fee is charged and refunded are in the <a class="text-navy-700 underline" href="{{ route('legal.show', 'refunds') }}">cancellation and refund policy</a>. Sending an enquiry is always free.</p>
        </div>
        @if ($services->isEmpty())
            <p class="card mt-6 p-6 text-sm text-muted">{{ $emptyMsg?->body ?: 'Service pages will appear here once published.' }} You can still <a class="text-navy-700 underline" href="{{ route('contact') }}">send us an enquiry</a>.</p>
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
    <x-page-blocks page="services" position="bottom" />
</x-layouts.public>
