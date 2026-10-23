@php $intro = \App\Models\PageBlock::builtIn('contact', 'intro'); $aside = \App\Models\PageBlock::builtIn('contact', 'aside'); @endphp
<x-layouts.public title="Contact" :description="$intro?->subheading ?: 'Send an enquiry to the team. We reply by email or phone as you prefer.'">
    <x-page-blocks page="contact" position="top" />
    <div class="container-page grid gap-8 py-10 lg:grid-cols-[1fr_320px]">
        <div>
            <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $intro?->heading ?: 'Contact us' }}</h1>
            <p class="mt-2 text-muted">{{ $intro?->body ?: 'Ask a question or tell us what help you need. Enquiries are free and never create a charge. Please do not include passport numbers or other sensitive details here.' }}</p>

            @if ($cycle)
                <p class="mt-4 rounded-md bg-navy-50 px-4 py-3 text-sm">About: <strong>{{ $cycle->programme->title }} {{ $cycle->cycle_key }}</strong></p>
            @endif

            <form method="POST" action="{{ route('enquiries.store') }}" class="card mt-6 space-y-4 p-6" novalidate>
                @csrf
                <input type="hidden" name="_ts" value="{{ $ts }}">
                @if ($cycle)<input type="hidden" name="cycle_id" value="{{ $cycle->id }}">@endif
                {{-- Honeypot: hidden from people, visible to bots --}}
                <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                <div>
                    <label for="category" class="label">What is your enquiry about?</label>
                    <select id="category" name="category" class="input @error('category') input-error @enderror" required>
                        @foreach (config('sas.enquiry_categories') as $key => $label)<option value="{{ $key }}" @selected(old('category', $category) === $key)>{{ $label }}</option>@endforeach
                    </select>
                    @error('category')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                @if ($services->isNotEmpty())
                    <div>
                        <label for="service" class="label">Service (optional)</label>
                        <select id="service" name="service" class="input">
                            <option value="">Not sure yet</option>
                            @foreach ($services as $s)<option value="{{ $s->slug }}" @selected(old('service', $serviceSlug) === $s->slug)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="name" class="label">Name</label><input id="name" name="name" value="{{ old('name', auth()->user()?->name) }}" required autocomplete="name" class="input @error('name') input-error @enderror">@error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                    <div><label for="email" class="label">Email</label><input id="email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required autocomplete="email" class="input @error('email') input-error @enderror">@error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                </div>
                <div x-data="{ pref: '{{ old('contact_preference', 'email') }}' }" class="grid gap-4 sm:grid-cols-2">
                    <fieldset>
                        <legend class="label">How should we contact you?</legend>
                        <label class="mr-4 inline-flex items-center gap-2 text-sm"><input type="radio" name="contact_preference" value="email" x-model="pref" class="text-navy-700"> Email</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="contact_preference" value="phone" x-model="pref" class="text-navy-700"> Phone</label>
                    </fieldset>
                    <div>
                        <label for="phone" class="label">Phone <span class="font-normal text-muted" x-text="pref === 'phone' ? '(required)' : '(optional)'"></span></label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone', auth()->user()?->phone) }}" autocomplete="tel" placeholder="+44 20 7946 0000" class="input @error('phone') input-error @enderror">
                        @error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="message" class="label">Message</label>
                    <textarea id="message" name="message" rows="6" required class="input @error('message') input-error @enderror">{{ old('message') }}</textarea>
                    @error('message')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-1 rounded border-slate-300 text-navy-700" @checked(old('consent'))>
                        <span>I agree that {{ config('sas.brand.short') }} may contact me about this enquiry. See the <a class="text-navy-700 underline" href="{{ route('legal.show', 'privacy') }}">privacy notice</a>.</span></label>
                    @error('consent')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <button class="btn-primary btn-lg">Send enquiry</button>
            </form>
        </div>
        @if (! $aside || $aside->enabled)
        <aside class="h-fit space-y-4 text-sm">
            <div class="card p-5">
                <h2 class="font-bold">{{ $aside?->heading ?: 'Other ways to reach us' }}</h2>
                <p class="mt-2 text-muted">{{ $aside?->body ?: 'Prefer to talk? Call, message us on WhatsApp or WeChat, or email the right team.' }}</p>
                <dl class="mt-4 space-y-3">
                    @if (config('sas.contact_phone'))<div><dt class="font-semibold">Phone</dt><dd><a class="text-navy-700 underline" href="tel:{{ preg_replace('/[^0-9+]/', '', config('sas.contact_phone')) }}">{{ config('sas.contact_phone') }}</a></dd></div>@endif
                    @if (config('sas.contact_whatsapp'))<div><dt class="font-semibold">WhatsApp</dt><dd><a class="text-navy-700 underline" rel="noopener" target="_blank" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', config('sas.contact_whatsapp')) }}">{{ config('sas.contact_whatsapp') }}</a></dd></div>@endif
                    @if (config('sas.contact_wechat'))<div><dt class="font-semibold">WeChat ID</dt><dd>{{ config('sas.contact_wechat') }}</dd></div>@endif
                    <div><dt class="font-semibold">Enquiries</dt><dd><a class="text-navy-700 underline" href="mailto:{{ config('sas.enquiry_email') }}">{{ config('sas.enquiry_email') }}</a></dd></div>
                    <div><dt class="font-semibold">Admissions</dt><dd><a class="text-navy-700 underline" href="mailto:{{ config('sas.admissions_email') }}">{{ config('sas.admissions_email') }}</a></dd></div>
                    <div><dt class="font-semibold">General</dt><dd><a class="text-navy-700 underline" href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a></dd></div>
                </dl>
                @if ($aside ? $aside->cta_label : true)
                    <p class="mt-4"><a class="text-navy-700 underline" href="{{ $aside ? \App\Domain\Site\Site::href($aside->cta_url) : route('consultations.create') }}">{{ $aside?->cta_label ?: 'Request a consultation' }}</a></p>
                @endif
            </div>
            <div class="card p-5">
                <h2 class="font-bold">Scan to chat</h2>
                <div class="mt-3 grid grid-cols-2 gap-4 text-center">
                    <figure><img src="{{ asset('images/contact/wechat-qr.jpg') }}" alt="WeChat QR code for {{ config('sas.contact_wechat') }}" class="mx-auto aspect-square w-full max-w-[9rem] rounded-lg border border-line" width="180" height="180" loading="lazy"><figcaption class="mt-2 text-xs text-muted">WeChat: {{ config('sas.contact_wechat') }}</figcaption></figure>
                    <figure><img src="{{ asset('images/contact/whatsapp-qr.jpg') }}" alt="WhatsApp QR code for {{ config('sas.contact_whatsapp') }}" class="mx-auto aspect-square w-full max-w-[9rem] rounded-lg border border-line" width="180" height="180" loading="lazy"><figcaption class="mt-2 text-xs text-muted">WhatsApp: {{ config('sas.contact_whatsapp') }}</figcaption></figure>
                </div>
            </div>
        </aside>
        @endif
    </div>    <x-page-blocks page="contact" position="bottom" />
</x-layouts.public>
