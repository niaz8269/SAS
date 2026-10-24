<x-layouts.public :title="$service->name" :description="\Illuminate\Support\Str::limit($service->summary, 155)">
    <section class="border-b border-line bg-white"><div class="container-page py-8">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('services.index') }}">Our services</a> / <span aria-current="page">{{ $service->name }}</span></nav>
        <p class="eyebrow mt-3">{{ ucfirst(str_replace('_', ' ', $service->category)) }}</p>
        <h1 class="mt-1 text-3xl font-extrabold sm:text-4xl">{{ $service->name }}</h1>
        <p class="mt-3 text-lg text-muted">{{ $service->summary }}</p>
    </div></section>

    <div class="container-page grid gap-8 py-8 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            @if ($service->audience)<section class="card p-6"><h2 class="text-lg font-bold">Who it is for</h2><p class="mt-2">{{ $service->audience }}</p></section>@endif
            @if ($service->description)<section class="card p-6 prose-sas">{!! \App\Support\Html::clean($service->description) !!}</section>@endif
            @foreach ([['Deliverables', $service->deliverables], ['What we need from you', $service->required_inputs], ['How it works', $service->process_steps], ['Exclusions (not included)', $service->exclusions]] as [$title, $items])
                @if (! empty($items))
                    <section class="card p-6"><h2 class="text-lg font-bold">{{ $title }}</h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach ($items as $i)<li>{{ $i }}</li>@endforeach</ul></section>
                @endif
            @endforeach
        </div>
        <aside class="card h-fit space-y-3 p-5 text-sm">
            <h2 class="text-lg font-bold">At a glance</h2>
            <p><span class="font-semibold">Price:</span> {{ $service->priceLabel() }}</p>
            <p><span class="font-semibold">Turnaround:</span> {{ $service->turnaround_basis ?: 'Confirmed after we review your request' }}</p>
            @if ($service->revision_limit !== null)<p><span class="font-semibold">Included revisions:</span> {{ $service->revision_limit }}</p>@endif
            @if (! $service->is_available)<p class="badge-warn">Currently unavailable</p>@endif
            <a href="{{ route('contact', ['service' => $service->slug, 'category' => $service->category]) }}" class="btn-primary w-full">Request a quotation</a>
            <p class="text-xs text-muted">Sending an enquiry is free and does not commit you to anything. Online ordering is not available yet.</p>
        </aside>
    </div>
</x-layouts.public>
