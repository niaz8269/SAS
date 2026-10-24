<x-layouts.public title="Share your story" description="Tell other students how you found your scholarship and what studying abroad is like. We read every story before it is published." :noindex="false">
    <section class="container-page py-8 sm:py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-muted"><a class="hover:underline" href="{{ route('home') }}">Home</a> / <a class="hover:underline" href="{{ route('stories.index') }}">Student stories</a> / <span aria-current="page">Share your story</span></nav>
        <div class="mt-4 grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div>
                <h1 class="text-3xl font-extrabold sm:text-4xl">Share your story</h1>
                <p class="mt-2 max-w-3xl text-muted">Did you win a scholarship or study abroad? Tell other students how you did it. Our team reads every story first, and nothing is published without your permission. Please do not include passport numbers, phone numbers or other private details.</p>

                <form method="POST" action="{{ route('stories.store') }}" enctype="multipart/form-data" class="card mt-6 space-y-5 p-5 sm:p-6" novalidate>
                    @csrf
                    <div class="hidden" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="name" class="label">Your name, as you want it shown</label>
                            <input id="name" name="name" value="{{ old('name') }}" maxlength="80" required class="input @error('name') input-error @enderror" autocomplete="name">
                            <p class="field-hint">You may use your first name only.</p>
                            @error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email" class="label">Your email (never shown)</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="190" required class="input @error('email') input-error @enderror" autocomplete="email">
                            <p class="field-hint">We only use it to write to you about your story.</p>
                            @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <fieldset>
                        <legend class="label">You are</legend>
                        <div class="flex flex-wrap gap-5">
                            @foreach (\App\Models\Testimonial::GENDERS as $value => $label)
                                <label class="inline-flex items-center gap-2"><input type="radio" name="gender" value="{{ $value }}" @checked(old('gender') === $value) required> {{ $label }}</label>
                            @endforeach
                        </div>
                        <p class="field-hint">Used only to choose the sketch picture if you do not add a photo.</p>
                        @error('gender')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </fieldset>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="nationality" class="label">Nationality</label>
                            <select id="nationality" name="nationality" required class="input @error('nationality') input-error @enderror">
                                <option value="">Choose your nationality</option>
                                @foreach ($countries as $c)<option value="{{ $c }}" @selected(old('nationality') === $c)>{{ $c }}</option>@endforeach
                            </select>
                            <p class="field-hint">The country of your passport.</p>
                            @error('nationality')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="study_country" class="label">Country where you got the scholarship</label>
                            <select id="study_country" name="study_country" required class="input @error('study_country') input-error @enderror">
                                <option value="">Choose the country</option>
                                @foreach ($countries as $c)<option value="{{ $c }}" @selected(old('study_country') === $c)>{{ $c }}</option>@endforeach
                            </select>
                            <p class="field-hint">If you study without a scholarship, choose the country where you study.</p>
                            @error('study_country')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="university" class="label">University</label>
                            <input id="university" name="university" value="{{ old('university') }}" maxlength="190" required class="input @error('university') input-error @enderror" placeholder="Where you study or studied">
                            @error('university')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="scholarship" class="label">Scholarship or programme <span class="font-normal text-muted">(optional)</span></label>
                            <input id="scholarship" name="scholarship" value="{{ old('scholarship') }}" maxlength="190" class="input" placeholder="For example Chinese Government Scholarship">
                        </div>
                    </div>
                    <div x-data="{ n: {{ mb_strlen((string) old('story')) }} }">
                        <label for="story" class="label">Your story</label>
                        <textarea id="story" name="story" rows="10" minlength="200" maxlength="3000" required x-on:input="n = $event.target.value.length" class="input @error('story') input-error @enderror" placeholder="How did you find the scholarship? What was hard? What is studying abroad like? What would you tell someone starting now?">{{ old('story') }}</textarea>
                        <p class="field-hint"><span x-text="n">0</span> of 3,000 characters (at least 200).</p>
                        @error('story')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="photo" class="label">Your photo <span class="font-normal text-muted">(optional)</span></label>
                        <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="input @error('photo') input-error @enderror">
                        <p class="field-hint">JPG, PNG or WebP, under 3 MB, at least 200 by 200 pixels. It is cropped to a square. If you add no photo, a sketch picture is used.</p>
                        @error('photo')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="flex items-start gap-3"><input type="checkbox" name="consent" value="1" class="mt-1" @checked(old('consent')) required>
                            <span class="text-sm">I agree that {{ config('sas.brand.short') }} may publish my story, my name as written above, my picture (or a sketch picture if I did not add one), my nationality, the country where I got my scholarship and my university on its website and social media after it has been reviewed. I understand that small mistakes may be corrected, that my email address will never be shown, and that I can ask for my story to be removed at any time by writing to <a class="underline" href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a>. See the <a class="underline" href="{{ route('legal.show', 'privacy') }}">privacy notice</a>.</span></label>
                        @error('consent')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn-primary btn-lg">Send my story</button>
                </form>
            </div>
            <aside class="space-y-4 lg:sticky lg:top-28 lg:self-start" aria-label="How it works">
                <section class="card p-5">
                    <h2 class="text-lg font-bold">What happens next</h2>
                    <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm">
                        <li>You send your story. We email you to confirm we have it.</li>
                        <li>Our team reads it. We may correct small spelling mistakes and will write to you if we need to change more.</li>
                        <li>If we publish it, it appears on the <a class="underline" href="{{ route('stories.index') }}">Student stories</a> page and on the home page.</li>
                    </ol>
                </section>
                <section class="card p-5 text-sm">
                    <h2 class="text-lg font-bold">Tips for a good story</h2>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Write in your own words and be honest.</li>
                        <li>Say what helped you and what you would do differently.</li>
                        <li>Keep it about you: no names of other people, no private details.</li>
                    </ul>
                </section>
            </aside>
        </div>
    </section>
</x-layouts.public>
