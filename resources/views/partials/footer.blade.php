@php
    $explore = \App\Domain\Site\Site::menu('footer_explore');
    $policies = \App\Domain\Site\Site::menu('footer_policies');
    $social = array_filter(['Facebook' => \App\Domain\Site\Site::get('social_facebook'), 'X' => \App\Domain\Site\Site::get('social_x'), 'Instagram' => \App\Domain\Site\Site::get('social_instagram'),
        'LinkedIn' => \App\Domain\Site\Site::get('social_linkedin'), 'YouTube' => \App\Domain\Site\Site::get('social_youtube')]);
    $phone = config('sas.contact_phone');
    $whatsapp = config('sas.contact_whatsapp');
    $wechat = config('sas.contact_wechat');
    $address = \App\Domain\Site\Site::get('contact_address');
@endphp
<footer class="mt-16 bg-navy-900 text-navy-100">
    <div class="container-page grid gap-10 py-12 md:grid-cols-4">
        <div class="md:col-span-1">
            <x-brand light />
            <p class="mt-4 text-sm text-navy-200">{{ config('sas.brand.tagline') }}</p>
            <p class="mt-4 text-xs text-navy-300">{{ config('sas.legal_entity') }}</p>
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
                @if ($phone)<li>Phone: <a class="hover:underline" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></li>@endif
                @if ($whatsapp)<li>WhatsApp: <a class="hover:underline" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $whatsapp) }}" rel="noopener" target="_blank">{{ $whatsapp }}</a></li>@endif
                @if ($wechat)<li>WeChat: {{ $wechat }}</li>@endif
                <li>Enquiries: <a class="hover:underline" href="mailto:{{ config('sas.enquiry_email') }}">{{ config('sas.enquiry_email') }}</a></li>
                <li>Admissions: <a class="hover:underline" href="mailto:{{ config('sas.admissions_email') }}">{{ config('sas.admissions_email') }}</a></li>
                <li>General: <a class="hover:underline" href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a></li>
                @if ($address)<li class="whitespace-pre-line">{{ $address }}</li>@endif
                <li><a class="hover:underline" href="{{ route('contact') }}">Send an enquiry</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-navy-800">
        <p class="container-page py-4 text-xs text-navy-300">
            &copy; {{ now()->year }} {{ config('sas.brand.name') }}. Scholarship details are summarised from official sources and can change: always confirm the latest details with us or the funder.
            SAS is an information and support service. It does not guarantee scholarship selection, admission or visa approval.
            @if ($note = \App\Domain\Site\Site::get('footer_note')) {{ $note }} @endif
        </p>
    </div>
</footer>
