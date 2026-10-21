@php($langs = old('language_evidence', $profile->language_evidence ?? []))
<x-layouts.student title="Profile">
    <h1 class="text-2xl font-extrabold">Your profile</h1>
    <p class="mt-1 text-sm text-muted">Everything here is optional. It is used only to show how scholarships compare with your details. Unknown stays unknown: we never guess.</p>

    <form method="POST" action="{{ route('student.profile.update') }}" class="mt-5 space-y-6" novalidate>
        @csrf @method('PUT')
        <section class="card space-y-4 p-5"><h2 class="text-lg font-bold">About you</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="name" class="label">Name</label><input id="name" name="name" required value="{{ old('name', $user->name) }}" class="input @error('name') input-error @enderror">@error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                <div><label for="phone" class="label">Phone (only needed for calls)</label><input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="input @error('phone') input-error @enderror">@error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                <div><label for="timezone" class="label">Time zone</label><select id="timezone" name="timezone" class="input">@foreach (\DateTimeZone::listIdentifiers() as $z)<option value="{{ $z }}" @selected(old('timezone', $user->timezone) === $z)>{{ $z }}</option>@endforeach</select></div>
                <div><label for="nat" class="label">Nationality</label><select id="nat" name="nationality_country_id" class="input"><option value="">Not provided</option>@foreach ($countries as $c)<option value="{{ $c->id }}" @selected(old('nationality_country_id', $profile->nationality_country_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div><label for="res" class="label">Country of residence</label><select id="res" name="residence_country_id" class="input"><option value="">Not provided</option>@foreach ($countries as $c)<option value="{{ $c->id }}" @selected(old('residence_country_id', $profile->residence_country_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div><label for="dob" class="label">Date of birth (optional)</label><input id="dob" type="date" name="date_of_birth" value="{{ old('date_of_birth', $profile->date_of_birth?->toDateString()) }}" class="input"><p class="field-hint">Only needed if a scholarship has an age condition.</p></div>
            </div>
        </section>

        <section class="card space-y-4 p-5"><h2 class="text-lg font-bold">Study plans</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="deg" class="label">Desired degree</label><select id="deg" name="desired_degree_level_id" class="input"><option value="">Not provided</option>@foreach ($degrees as $d)<option value="{{ $d->id }}" @selected(old('desired_degree_level_id', $profile->desired_degree_level_id) == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
                <div><label for="intake" class="label">Intended intake year</label><input id="intake" type="number" name="intake_year" min="2026" max="2040" value="{{ old('intake_year', $profile->intake_year) }}" class="input"></div>
                <fieldset><legend class="label">Preferred destinations</legend><div class="max-h-40 space-y-1 overflow-y-auto">@forelse ($destinations as $c)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="preferred_destination_ids[]" value="{{ $c->id }}" class="rounded border-slate-300 text-forest-700" @checked(in_array($c->id, old('preferred_destination_ids', $profile->preferred_destination_ids ?? [])))> {{ $c->name }}</label>@empty<p class="text-xs text-muted">No destinations published yet.</p>@endforelse</div></fieldset>
                <fieldset><legend class="label">Subject interests</legend><div class="max-h-40 space-y-1 overflow-y-auto">@forelse ($subjects as $s)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="subject_interest_ids[]" value="{{ $s->id }}" class="rounded border-slate-300 text-forest-700" @checked(in_array($s->id, old('subject_interest_ids', $profile->subject_interest_ids ?? [])))> {{ $s->name }}</label>@empty<p class="text-xs text-muted">No subjects published yet.</p>@endforelse</div></fieldset>
                <div class="sm:col-span-2"><label for="budget" class="label">Budget note (optional)</label><input id="budget" name="budget_note" value="{{ old('budget_note', $profile->budget_note) }}" class="input"></div>
            </div>
        </section>

        <section class="card space-y-4 p-5"><h2 class="text-lg font-bold">Academic background</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="pq" class="label">Prior qualification</label><input id="pq" name="prior_qualification" value="{{ old('prior_qualification', $profile->prior_qualification) }}" class="input"></div>
                <div><label for="inst" class="label">Institution</label><input id="inst" name="institution" value="{{ old('institution', $profile->institution) }}" class="input"></div>
                <div><label for="cy" class="label">Completion year</label><input id="cy" type="number" name="completion_year" value="{{ old('completion_year', $profile->completion_year) }}" class="input"></div>
                <div></div>
                <div><label for="gpa" class="label">Grade (exactly as on your transcript)</label><input id="gpa" name="gpa_value" value="{{ old('gpa_value', $profile->gpa_value) }}" class="input"></div>
                <div><label for="scale" class="label">Grading scale</label><input id="scale" name="gpa_scale" value="{{ old('gpa_scale', $profile->gpa_scale) }}" placeholder="e.g. CGPA /4.0, Percentage, UK classification" class="input"><p class="field-hint">We never convert between scales.</p></div>
            </div>
            <fieldset><legend class="label">Language evidence</legend>
                @for ($i = 0; $i < 3; $i++)
                    <div class="mb-2 grid grid-cols-2 gap-2">
                        <div><label class="sr-only" for="lt{{ $i }}">Test {{ $i + 1 }}</label><input id="lt{{ $i }}" name="language_evidence[{{ $i }}][test]" placeholder="Test, e.g. IELTS" value="{{ $langs[$i]['test'] ?? '' }}" class="input"></div>
                        <div><label class="sr-only" for="ls{{ $i }}">Score {{ $i + 1 }}</label><input id="ls{{ $i }}" name="language_evidence[{{ $i }}][score]" placeholder="Score, e.g. 6.5" value="{{ $langs[$i]['score'] ?? '' }}" class="input"></div>
                    </div>
                @endfor
            </fieldset>
        </section>
        <button class="btn-primary btn-lg">Save profile</button>
    </form>
</x-layouts.student>
