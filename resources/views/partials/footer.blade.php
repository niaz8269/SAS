<footer class="mt-16 bg-forest-900 text-forest-100">
    <div class="container-page grid gap-10 py-12 md:grid-cols-4">
        <div class="md:col-span-1">
            <x-brand light />
            <p class="mt-4 text-sm text-forest-200">{{ config('sas.brand.tagline') }}</p>
            <p class="mt-4 text-xs text-forest-300">{{ config('sas.legal_entity') }}</p>
        </div>
        <nav aria-label="Explore" class="text-sm">
            <h2 class="mb-3 text-sm font-semibold text-white">Explore</h2>
            <ul class="space-y-2">
                <li><a class="hover:underline" href="{{ route('scholarships.index') }}">Scholarships</a></li>
                <li><a class="hover:underline" href="{{ route('destinations.index') }}">Study destinations</a></li>
                <li><a class="hover:underline" href="{{ route('services.index') }}">Our services</a></li>
                <li><a class="hover:underline" href="{{ route('resources.index') }}">Resources</a></li>
                <li><a class="hover:underline" href="{{ route('partners') }}">For partner organisations</a></li>
            </ul>
        </nav>
        <nav aria-label="Policies" class="text-sm">
            <h2 class="mb-3 text-sm font-semibold text-white">Policies</h2>
            <ul class="space-y-2">
                <li><a class="hover:underline" href="{{ route('legal.show', 'privacy') }}">Privacy</a></li>
                <li><a class="hover:underline" href="{{ route('legal.show', 'terms') }}">Terms</a></li>
                <li><a class="hover:underline" href="{{ route('legal.show', 'refunds') }}">Cancellation and refunds</a></li>
                <li><a class="hover:underline" href="{{ route('legal.show', 'accessibility') }}">Accessibility</a></li>
                <li><a class="hover:underline" href="{{ route('corrections.create') }}">Accuracy and corrections</a></li>
            </ul>
        </nav>
        <div class="text-sm">
            <h2 class="mb-3 text-sm font-semibold text-white">Contact</h2>
            <ul class="space-y-2">
                <li><a class="hover:underline" href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a></li>
                <li><a class="hover:underline" href="{{ route('contact') }}">Send an enquiry</a></li>
                <li>Accessibility: <a class="hover:underline" href="mailto:{{ config('sas.accessibility_email') }}">{{ config('sas.accessibility_email') }}</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-forest-800">
        <p class="container-page py-4 text-xs text-forest-300">
            &copy; {{ now()->year }} {{ config('sas.brand.name') }}. Scholarship details are summarised from official sources and can change: always confirm on the official website.
            SAS is an information and support service. It does not guarantee scholarship selection, admission or visa approval.
        </p>
    </div>
</footer>