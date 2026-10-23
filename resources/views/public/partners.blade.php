<x-layouts.public title="For partner organisations" description="Universities, funders and institutions: how to have a scholarship listed.">
    <div class="container-page max-w-3xl py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">For partner organisations</h1>
        <p class="mt-3 text-muted">Universities, funders and institutions can ask us to list a scholarship. Every listing is verified against an official source and reviewed by our editorial team before it is published. Partners cannot publish directly and cannot see student information.</p>
        <div class="card mt-6 p-6">
            <h2 class="text-lg font-bold">How it works today</h2>
            <p class="mt-2 text-sm">Our editors enter partner listings on your behalf. Send us the programme details and the official page where the call is published.</p>
            <p class="mt-2 text-sm text-muted">A self-service partner portal is planned for a later release. It is not available yet.</p>
            <a class="btn-primary mt-4" href="{{ route('contact', ['category' => 'general']) }}">Contact the editorial team</a>
        </div>
        <p class="mt-6 text-sm">Already registered as a partner manager? <a class="text-navy-700 underline" href="{{ route('partner.home') }}">Go to the partner area</a>.</p>
    </div>
</x-layouts.public>
