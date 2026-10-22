@php
    $explore = \App\Domain\Site\Site::menu('footer_explore');
    $policies = \App\Domain\Site\Site::menu('footer_policies');
    $social = array_filter(['Facebook' => \App\Domain\Site\Site::get('social_facebook'), 'X' => \App\Domain\Site\Site::get('social_x'), 'Instagram' => \App\Domain\Site\Site::get('social_instagram'),
        'LinkedIn' => \App\Domain\Site\Site::get('social_linkedin'), 'YouTube' => \App\Domain\Site\Site::get('social_youtube')]);
    $phone = \App\Domain\Site\Site::get('contact_phone');
    $address = \App\Domain\Site\Site::get('contact_address');
@endphp
<footer class="mt-16 bg-forest-900 text-forest-100">
    <div class="container-page grid gap-10 py-12 md:grid-cols-4">
        <div class="md:col-span-1">
            <x-brand light />
            <p class="mt-4 text-sm text-forest-200">{{ config('sas.brand.tagline') }}</p>
            <p class="mt-4 text-xs text-forest-300">{{ config('sas.legal_entity') }}</p>
            @if ($social)
                <ul class="mt-4 flex flex-wrap gap-3 text-sm">
                    @foreach ($social as $name => $url)
                        <li><a class="underline hover:no-underline" href="{{ \App\Domain\Site\Site::href($url) }}" rel="noopener" target="_blank">{{ $name }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
        <nav aria-label="Explore" class="text-sm">
            <h2 class="mb-3 text-sm font-semibold text-white">Explore</h2>
            <ul class="space-y-2">
                @foreach ($explore as $item)
                    <li><a class="hover:underline" href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <nav aria-label="Policies" class="text-sm">
            <h2 class="mb-3 text-sm font-semibold text-white">Policies</h2>
            <ul class="space-y-2">
                @foreach ($policies as $item)
                    <li><a class="hover:underline" href="{{ $item['url'] }}" @if($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <div class="text-sm">
            <h2 class="mb-3 text-sm font-semibold text-white">Contact</h2>
            <ul class="space-y-2">
                <li><a class="hover:underline" href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a></li>
                @if ($phone)<li><a class="hover:underline" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></li>@endif
                @if ($address)<li class="whitespace-pre-line">{{ $address }}</li>@endif
                <li><a class="hover:underline" href="{{ route('contact') }}">Send an enquiry</a></li>
                <li>Accessibility: <a class="hover:underline" href="mailto:{{ config('sas.accessibility_email') }}">{{ config('sas.accessibility_email') }}</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-forest-800">
        <p class="container-page py-4 text-xs text-forest-300">
            &copy; {{ now()->year }} {{ config('sas.brand.name') }}. Scholarship details are summarised from official sources and can change: always confirm on the official website.
            SAS is an information and support service. It does not guarantee scholarship selection, admission or visa approval.
            @if ($note = \App\Domain\Site\Site::get('footer_note')) {{ $note }} @endif
        </p>
    </div>
</footer>
