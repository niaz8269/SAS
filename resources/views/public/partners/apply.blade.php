<x-layouts.public title="Be a partner" description="Recruiting agencies and organisations: apply to become a partner. Tell us about your agency and yourself; once our team approves, you get your own partner account." :noindex="false">
    <section class="container-page py-8 sm:py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <span aria-current="page">Be a partner</span></nav>
        <div class="mt-4 grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div>
                <h1 class="text-3xl font-extrabold sm:text-4xl">Be a partner</h1>
                <p class="mt-2 max-w-3xl text-muted">Do you advise students who want to study abroad? Tell us about your agency and about yourself. Our team reviews every application. When it is approved, you get your own partner (agent) account where you can add your students and follow their applications.</p>

                <form method="POST" action="{{ route('partners.store') }}" class="mt-6 space-y-6" novalidate>
                    @csrf
                    <div class="hidden" aria-hidden="true"><label>Leave this empty <input type="text" name="company_site" tabindex="-1" autocomplete="off"></label></div>

                    <fieldset class="card space-y-5 p-5 sm:p-6">
                        <legend class="px-1 text-lg font-bold">Your agency</legend>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="agency_name" class="label">Agency name</label>
                                <input id="agency_name" name="agency_name" value="{{ old('agency_name') }}" maxlength="200" required class="input @error('agency_name') input-error @enderror" autocomplete="organization">
                                @error('agency_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="agency_country" class="label">Country</label>
                                <select id="agency_country" name="agency_country" required class="input @error('agency_country') input-error @enderror">
                                    <option value="">Choose the country</option>
                                    @foreach ($countries as $c)<option value="{{ $c }}" @selected(old('agency_country') === $c)>{{ $c }}</option>@endforeach
                                </select>
                                @error('agency_country')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="city" class="label">City <span class="font-normal text-muted">(optional)</span></label>
                                <input id="city" name="city" value="{{ old('city') }}" maxlength="120" class="input" autocomplete="address-level2">
                            </div>
                            <div>
                                <label for="website" class="label">Website <span class="font-normal text-muted">(optional)</span></label>
                                <input id="website" type="url" name="website" value="{{ old('website') }}" maxlength="300" placeholder="https://" class="input @error('website') input-error @enderror" autocomplete="url">
                                @error('website')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="agency_phone" class="label">Agency phone or WhatsApp</label>
                                <input id="agency_phone" type="tel" name="agency_phone" value="{{ old('agency_phone') }}" maxlength="40" required class="input @error('agency_phone') input-error @enderror" autocomplete="tel">
                                <p class="field-hint">With the country code, for example +92.</p>
                                @error('agency_phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="agency_email" class="label">Agency email <span class="font-normal text-muted">(optional)</span></label>
                                <input id="agency_email" type="email" name="agency_email" value="{{ old('agency_email') }}" maxlength="190" class="input @error('agency_email') input-error @enderror">
                                @error('agency_email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="registration_number" class="label">Business registration number <span class="font-normal text-muted">(optional)</span></label>
                                <input id="registration_number" name="registration_number" value="{{ old('registration_number') }}" maxlength="120" class="input">
                                <p class="field-hint">If your agency is registered. We may ask for a copy later.</p>
                            </div>
                            <div>
                                <label for="years_in_business" class="label">Years in business <span class="font-normal text-muted">(optional)</span></label>
                                <select id="years_in_business" name="years_in_business" class="input">
                                    <option value="">Choose</option>
                                    @foreach (\App\Models\PartnerApplication::YEARS as $value => $label)<option value="{{ $value }}" @selected(old('years_in_business') === $value)>{{ $label }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="students_per_year" class="label">Students you advise each year <span class="font-normal text-muted">(optional)</span></label>
                                <select id="students_per_year" name="students_per_year" class="input">
                                    <option value="">Choose</option>
                                    @foreach (\App\Models\PartnerApplication::STUDENTS as $value => $label)<option value="{{ $value }}" @selected(old('students_per_year') === $value)>{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label for="markets" class="label">Countries your students come from <span class="font-normal text-muted">(optional)</span></label>
                                <input id="markets" name="markets" value="{{ old('markets') }}" maxlength="300" class="input" placeholder="For example Pakistan, Afghanistan">
                            </div>
                        </div>
                        <div x-data="{ n: {{ mb_strlen((string) old('about')) }} }">
                            <label for="about" class="label">About your agency <span class="font-normal text-muted">(optional)</span></label>
                            <textarea id="about" name="about" rows="5" maxlength="2000" x-on:input="n = $event.target.value.length" class="input @error('about') input-error @enderror" placeholder="What do you do for students? Which countries and universities do you work with?">{{ old('about') }}</textarea>
                            <p class="field-hint"><span x-text="n">0</span> of 2,000 characters.</p>
                            @error('about')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                    </fieldset>

                    <fieldset class="card space-y-5 p-5 sm:p-6">
                        <legend class="px-1 text-lg font-bold">About you</legend>
                        <p class="text-sm text-muted">The person who will use the partner account.</p>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="applicant_name" class="label">Your full name</label>
                                <input id="applicant_name" name="applicant_name" value="{{ old('applicant_name') }}" maxlength="120" required class="input @error('applicant_name') input-error @enderror" autocomplete="name">
                                @error('applicant_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="applicant_position" class="label">Your position</label>
                                <input id="applicant_position" name="applicant_position" value="{{ old('applicant_position') }}" maxlength="120" required class="input @error('applicant_position') input-error @enderror" placeholder="For example Director" autocomplete="organization-title">
                                @error('applicant_position')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="applicant_email" class="label">Your email</label>
                                <input id="applicant_email" type="email" name="applicant_email" value="{{ old('applicant_email') }}" maxlength="190" required class="input @error('applicant_email') input-error @enderror" autocomplete="email">
                                <p class="field-hint">This will be your sign-in email. We send the approval here.</p>
                                @error('applicant_email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="applicant_phone" class="label">Your phone or WhatsApp</label>
                                <input id="applicant_phone" type="tel" name="applicant_phone" value="{{ old('applicant_phone') }}" maxlength="40" required class="input @error('applicant_phone') input-error @enderror" autocomplete="tel">
                                @error('applicant_phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div>
                            <label for="how_heard" class="label">How did you hear about us? <span class="font-normal text-muted">(optional)</span></label>
                            <input id="how_heard" name="how_heard" value="{{ old('how_heard') }}" maxlength="190" class="input">
                        </div>
                        <div>
                            <label class="flex items-start gap-3"><input type="checkbox" name="consent" value="1" class="mt-1" @checked(old('consent')) required>
                                <span class="text-sm">I confirm that the information above is true and that I am allowed to apply for this agency. I agree that {{ config('sas.brand.short') }} may use it to review my application and to contact me. See the <a class="underline" href="{{ route('legal.show', 'privacy') }}">privacy notice</a>.</span></label>
                            @error('consent')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <button class="btn-primary btn-lg">Send my application</button>
                    </fieldset>
                </form>
            </div>
            <aside class="space-y-4 lg:sticky lg:top-28 lg:self-start" aria-label="How it works">
                <section class="card p-5">
                    <h2 class="text-lg font-bold">What happens next</h2>
                    <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm">
                        <li>You send this form. We email you to confirm we have it.</li>
                        <li>Our team reviews your agency. We may write to you for more information.</li>
                        <li>If it is approved, we email you a link to choose your own password.</li>
                        <li>You sign in to your partner account and add your students.</li>
                    </ol>
                </section>
                <section class="card p-5 text-sm">
                    <h2 class="text-lg font-bold">Already have an account?</h2>
                    <p class="mt-2">Partners <a class="underline" href="{{ route('login') }}">sign in here</a>.</p>
                </section>
            </aside>
        </div>
    </section>
</x-layouts.public>
