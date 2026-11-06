<x-dynamic-component :component="$ctx->layout()" :title="'Your details'">
    @if ($plan)
        @include('student.wizard._header')
    @else
        <h1 class="text-2xl font-extrabold">Your details{{ $ctx->isAgent() ? ' for '.$student->name : '' }}</h1>
    @endif

    <div class="mt-5 flex items-start gap-3 rounded-xl border border-gold bg-gold/10 p-4 text-base text-ink">
        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-navy-700 text-sm font-bold text-white" aria-hidden="true">i</span>
        <div>Fill this in <strong>once</strong>: every scholarship {{ $ctx->isAgent() ? 'this student applies' : 'you apply' }} for uses these same answers, so you do not type them again for the next one. Fields marked <span class="font-bold text-alert">*</span> are needed before you can send the application to SAS. Press <strong>Save</strong> at the bottom before you leave this page. {{ $percent }}% of the required answers are filled in.@if ($ctx->isAgent()) The answers belong to the student, who can also sign in and see them.@endif</div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-[12.5rem_1fr]">
        <nav aria-label="Form sections" class="lg:sticky lg:top-24 lg:self-start">
            <ul class="card overflow-hidden text-sm">
                @foreach ($sections as $key => $section)
                    @php($p = $progress[$key])
                    <li class="border-b border-line last:border-0"><a href="#{{ $key }}" class="flex items-center justify-between gap-2 px-3 py-2.5 hover:bg-navy-50"><span>{{ $section['title'] }}</span>@if ($p['required'] && $p['answered'] >= $p['required'])<span class="font-bold text-navy-700" aria-label="done">✓</span>@elseif ($p['required'])<span class="rounded-full bg-gold/20 px-2 text-xs font-semibold text-ink" aria-label="{{ $p['required'] - $p['answered'] }} to answer">{{ $p['required'] - $p['answered'] }}</span>@endif</a></li>
                @endforeach
                <li><a href="#declaration" class="flex items-center justify-between gap-2 px-3 py-2.5 hover:bg-navy-50"><span>Declaration</span>@if ($form->value('_declared_at'))<span class="font-bold text-navy-700" aria-label="done">✓</span>@endif</a></li>
            </ul>
        </nav>

        <form method="POST" action="{{ $plan ? $ctx->route('applications.form.update', $plan) : $ctx->route('form.update') }}" novalidate>
            @csrf @method('PUT')
            <fieldset @disabled($locked) class="space-y-4 border-0 p-0">
                <legend class="sr-only">Application form</legend>
                @foreach ($sections as $key => $section)
                    <section id="{{ $key }}" class="card scroll-mt-24" aria-labelledby="{{ $key }}-h">
                        <h2 id="{{ $key }}-h" class="border-b border-line px-4 py-3 text-lg font-bold">{{ $section['title'] }}</h2>
                        <div class="p-4">
                            @if (isset($section['note']))<p class="mb-3 rounded-lg border border-gold bg-gold/10 px-3 py-2 text-base text-ink">{{ $section['note'] }}</p>@endif
                            @if (isset($section['repeat']))
                                @php($rows = $form->value($key, []))
                                @for ($i = 0; $i < $section['repeat']; $i++)
                                    <div class="{{ $i > 0 ? 'mt-4 border-t border-dashed border-line pt-4' : '' }}">
                                        <p class="mb-2 text-sm font-semibold text-muted">Entry {{ $i + 1 }}</p>
                                        <div class="grid gap-x-6 gap-y-4 lg:grid-cols-2">
                                            @foreach ($section['fields'] as $name => $def)
                                                @include('student._form-field', ['id' => "$key-$i-$name", 'field' => "sections[$key][rows][$i][$name]", 'def' => ($i === 0 && in_array($name, $section['required_first'] ?? [], true)) ? array_merge($def, ['required' => true]) : $def, 'value' => old("sections.$key.rows.$i.$name", $rows[$i][$name] ?? null), 'errorKey' => "sections.$key.rows.$i.$name", 'countries' => $countries])
                                            @endforeach
                                        </div>
                                    </div>
                                @endfor
                            @else
                                <div class="grid gap-x-6 gap-y-4 lg:grid-cols-2">
                                    @foreach ($section['fields'] as $name => $def)
                                        @include('student._form-field', ['id' => "$key-$name", 'field' => "sections[$key][$name]", 'def' => $def, 'value' => old("sections.$key.$name", $form->value($name)), 'errorKey' => "sections.$key.$name", 'countries' => $countries])
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                @endforeach

                <section id="declaration" class="card scroll-mt-24" aria-labelledby="decl-h">
                    <h2 id="decl-h" class="border-b border-line px-4 py-3 text-lg font-bold">Declaration</h2>
                    <div class="p-4 text-base">
                        <label class="flex items-start gap-3"><input type="checkbox" name="declared" value="1" class="mt-1 h-4 w-4" @checked(old('declared', (bool) $form->value('_declared_at')))> <span><span class="text-alert" aria-hidden="true">*</span> I hereby affirm that: {{ config('application_form.declaration') }}</span></label>
                        @if ($form->value('_declared_at'))<p class="mt-2 text-sm text-muted">Recorded on {{ \Illuminate\Support\Carbon::parse($form->value('_declared_at'))->format('j M Y') }}.</p>@endif
                    </div>
                </section>
            </fieldset>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <span>@if ($plan)<a class="btn-outline" href="{{ $ctx->route('applications.show', $plan) }}">Previous</a>@endif</span>
                <span class="flex flex-wrap items-center gap-3">
                    @unless ($locked)<button class="btn-primary">Save</button>@endunless
                    @if ($plan)<a class="btn-outline" href="{{ $ctx->route('applications.materials', $plan) }}">Next: Documents</a>@endif
                </span>
            </div>
        </form>
    </div>
</x-dynamic-component>
