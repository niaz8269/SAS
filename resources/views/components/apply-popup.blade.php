@props(['subjects'])
@php
    // Plain lists only: cached values must not hold models (the production cache refuses objects).
    $options = \Illuminate\Support\Facades\Cache::remember('apply.popup.options', 3600, fn () => [
        'degrees' => \App\Models\DegreeLevel::orderBy('id')->pluck('name', 'code')->all(),
        'countries' => \App\Models\Country::orderBy('name')->pluck('name', 'slug')->all(),
        'scholarships' => \App\Models\ScholarshipCycle::visible()->count(),
    ]);
    $ts = \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp);
@endphp
{{-- Enquiry pop-up for visitors who are not signed in. It opens a moment after the page loads (every load), and closes with the cross, Escape or a click outside.
     It opens only after the page has loaded, and never for automated browsers. The home page does not depend on it: everything on the page works without it, and the contact page has the full form. --}}
<div x-data="{
        open: false, sent: false, busy: false, errors: {}, reference: null, failed: '',
        init() { if (! navigator.webdriver) { setTimeout(() => { this.open = true }, 1200) } },
        close() { this.open = false },
        async submit(event) {
            this.busy = true; this.errors = {}; this.failed = '';
            try {
                const response = await fetch(event.target.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(event.target) });
                if (response.status === 422) { this.errors = (await response.json()).errors || {} }
                else if (response.ok) { this.reference = (await response.json()).reference; this.sent = true }
                else { this.failed = 'Something went wrong. Please try again, or use the contact page.' }
            } catch (error) { this.failed = 'We could not send this. Check your connection and try again.' }
            this.busy = false;
        }
    }"
    @keydown.escape.window="close()">
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[70] flex items-end justify-center bg-ink/60 sm:items-center sm:p-6" @click.self="close()">
        <div role="dialog" aria-modal="true" aria-labelledby="apply-popup-title" x-trap.noscroll="open"
            class="relative grid max-h-[92svh] w-full max-w-3xl overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:rounded-xl md:grid-cols-[17rem_1fr]">
            <button type="button" @click="close()" class="absolute right-3 top-3 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full text-muted hover:bg-slate-100 hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-navy-700" aria-label="Close">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>

            <aside class="hidden flex-col justify-between bg-navy-800 p-7 text-white md:flex">
                <div>
                    <x-brand :light="true" />
                    <h2 class="mt-6 text-2xl font-bold leading-snug text-white">Start your study abroad journey with {{ config('sas.brand.short') }}</h2>
                    <ul class="mt-5 space-y-3 text-sm leading-6 text-white/90">
                        @foreach ([
                            'Free to ask: an enquiry never creates a charge.',
                            $options['scholarships'].' scholarships from official sources, each with the date it was last checked.',
                            'Help with documents, applications and visas, when you need it.',
                        ] as $point)
                            <li class="flex gap-2"><svg class="mt-1 h-4 w-4 shrink-0 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg><span>{{ $point }}</span></li>
                        @endforeach
                    </ul>
                </div>
                <p class="mt-6 text-xs text-white/70">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</p>
            </aside>

            <div class="p-6 sm:p-8">
                <div x-show="! sent">
                    <h2 id="apply-popup-title" class="pr-8 text-2xl font-bold text-navy-800">Ask {{ config('sas.brand.short') }} about studying abroad</h2>
                    <p class="mt-1 text-sm text-muted">Tell us what you want to study and an adviser will contact you with scholarship options, requirements and deadlines.</p>

                    <form method="POST" action="{{ route('enquiries.quick') }}" @submit.prevent="submit($event)" class="mt-5 space-y-4" novalidate>
                        @csrf
                        <input type="hidden" name="_ts" value="{{ $ts }}">
                        <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="ap-degree" class="label">Degree</label>
                                <select id="ap-degree" name="degree" class="input"><option value="">Select degree</option>@foreach ($options['degrees'] as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select>
                                <p class="field-error" role="alert" x-show="errors.degree" x-text="errors.degree && errors.degree[0]"></p>
                            </div>
                            <div>
                                <label for="ap-subject" class="label">Subject</label>
                                <select id="ap-subject" name="subject" class="input"><option value="">Select subject</option>@foreach ($subjects as $subject)<option value="{{ $subject->slug }}">{{ $subject->name }}</option>@endforeach</select>
                                <p class="field-error" role="alert" x-show="errors.subject" x-text="errors.subject && errors.subject[0]"></p>
                            </div>
                            <div>
                                <label for="ap-name" class="label">Full name</label>
                                <input id="ap-name" name="name" required autocomplete="name" placeholder="Your full name" class="input" :class="errors.name && 'input-error'">
                                <p class="field-error" role="alert" x-show="errors.name" x-text="errors.name && errors.name[0]"></p>
                            </div>
                            <div>
                                <label for="ap-nationality" class="label">Nationality</label>
                                <select id="ap-nationality" name="nationality" required class="input" :class="errors.nationality && 'input-error'"><option value="">Select your country</option>@foreach ($options['countries'] as $slug => $name)<option value="{{ $slug }}">{{ $name }}</option>@endforeach</select>
                                <p class="field-error" role="alert" x-show="errors.nationality" x-text="errors.nationality && errors.nationality[0]"></p>
                            </div>
                            <div>
                                <label for="ap-email" class="label">Email</label>
                                <input id="ap-email" type="email" name="email" required autocomplete="email" placeholder="name@example.com" class="input" :class="errors.email && 'input-error'">
                                <p class="field-error" role="alert" x-show="errors.email" x-text="errors.email && errors.email[0]"></p>
                            </div>
                            <div>
                                <label for="ap-whatsapp" class="label">WhatsApp number <span class="font-normal text-muted">(optional)</span></label>
                                <input id="ap-whatsapp" type="tel" name="whatsapp" autocomplete="tel" placeholder="+00 000 000 0000" class="input" :class="errors.whatsapp && 'input-error'">
                                <p class="field-error" role="alert" x-show="errors.whatsapp" x-text="errors.whatsapp && errors.whatsapp[0]"></p>
                            </div>
                        </div>

                        <div>
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-1 rounded border-slate-300 text-navy-700">
                                <span>I agree that {{ config('sas.brand.short') }} may contact me about this enquiry. See the <a class="text-navy-700 underline" href="{{ route('legal.show', 'privacy') }}">privacy notice</a>.</span></label>
                            <p class="field-error" role="alert" x-show="errors.consent" x-text="errors.consent && errors.consent[0]"></p>
                        </div>

                        <p class="field-error" role="alert" x-show="failed" x-text="failed"></p>
                        <button type="submit" class="btn-primary btn-lg w-full" :disabled="busy" :aria-busy="busy"><span x-show="! busy">Send enquiry</span><span x-show="busy" x-cloak>Sending…</span></button>
                        <p class="text-xs text-muted">Your details are used only to contact you about this enquiry. Please do not include passport numbers or other sensitive details.</p>
                    </form>
                </div>

                <div x-show="sent" x-cloak role="status" class="py-6">
                    <h2 class="pr-8 text-2xl font-bold text-navy-800">Thank you, we have your enquiry</h2>
                    <p class="mt-2 text-base leading-7">An adviser will contact you using the details you gave. We also sent a confirmation to your email address.</p>
                    <p class="mt-2 text-sm text-muted" x-show="reference">Your reference: <strong x-text="reference"></strong></p>
                    <button type="button" @click="close()" class="btn-primary mt-5">Continue browsing</button>
                </div>
            </div>
        </div>
    </div>
</div>
