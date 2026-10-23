<x-layouts.public title="About" description="What SAS does, how listings are verified and what we do not promise.">
    <div class="container-page max-w-3xl py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">About {{ config('sas.brand.short') }}</h1>
        <p class="mt-3 text-lg text-muted">{{ config('sas.brand.name') }} helps students find scholarships and prepare strong applications. Reading about scholarships is always free and never needs an account.</p>

        <h2 class="mt-8 text-xl font-bold">How we keep listings trustworthy</h2>
        <ul class="mt-2 list-disc space-y-2 pl-6">
            <li>Every published call links to its official source and records who checked it and when.</li>
            <li>A second person reviews each listing before it is published. Editors cannot approve their own changes.</li>
            <li>Deadlines are stored with their original time zone. If a source gives a date but no time, we show the date only and never invent a closing time.</li>
            <li>Listings are rechecked on a schedule. Any listing overdue for a check is labelled "Needs recheck" and is not promoted.</li>
            <li>Paying for a service or a placement never buys verification or changes eligibility information. Sponsored placements are labelled.</li>
        </ul>

        <h2 class="mt-8 text-xl font-bold">What we do not do</h2>
        <ul class="mt-2 list-disc space-y-2 pl-6">
            <li>We do not cover every scholarship in the world. The directory is a curated selection.</li>
            <li>You apply on the official website of each scholarship. We do not submit applications for you and we never ask for your portal passwords.</li>
            <li>We do not guarantee scholarship selection, admission, attestation acceptance or visa approval.</li>
        </ul>

        <h2 class="mt-8 text-xl font-bold">Found a mistake?</h2>
        <p class="mt-2">Please <a class="text-navy-700 underline" href="{{ route('corrections.create') }}">tell us</a>. We check the official source and fix the listing.</p>
        <p class="mt-8 text-sm text-muted">{{ config('sas.legal_entity') }}</p>
    </div>
</x-layouts.public>
