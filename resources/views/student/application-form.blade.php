<x-dynamic-component :component="$ctx->layout()" :title="$plan ? 'Application form' : 'Your details'">
    @if ($plan)
        @include('student.wizard._header')
        <h2 class="mt-5 text-lg font-bold">Step 2: Application form</h2>
    @else
        <p class="text-sm font-semibold uppercase tracking-wide text-navy-700">Your details</p>
        <h1 class="text-2xl font-extrabold">Application form</h1>
    @endif
    <p class="mt-1 text-sm text-muted">Fill this in <strong>once</strong>. Every scholarship{{ $ctx->isAgent() ? ' this student applies' : ' you apply' }} for uses these answers. Fields marked <span class="text-alert">*</span> are needed before you can submit. Answers are private and stored encrypted. @if ($ctx->isAgent())They belong to the student, who can also sign in and see them.@else Your counsellor sees them only if you share them.@endif</p>

    <div class="card mt-4 p-4 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2"><strong>{{ $percent }}% of the required answers are filled in</strong>@if (! $missing)<span class="badge-green">✓ Complete</span>@endif</div>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-ivory-deep" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="Form progress"><div class="h-full bg-navy-600" style="width: {{ $percent }}%"></div></div>
    </div>

    <div class="mt-5 grid gap-6 lg:grid-cols-[200px_1fr]">
        <nav aria-label="Form sections" class="lg:sticky lg:top-6 lg:self-start">
            <ul class="card space-y-0.5 p-2 text-sm">
                @foreach ($sections as $key => $section)
                    @php($p = $progress[$key])
                    <li><a href="#{{ $key }}" class="flex items-center justify-between gap-2 rounded-md px-3 py-1.5 hover:bg-navy-50"><span>{{ $section['title'] }}</span>@if ($p['required'] && $p['answered'] >= $p['required'])<span class="text-navy-700" aria-label="done">✓</span>@elseif ($p['required'])<span class="text-xs text-alert" aria-label="{{ $p['required'] - $p['answered'] }} to answer">{{ $p['required'] - $p['answered'] }}</span>@endif</a></li>
                @endforeach
                <li><a href="#declaration" class="flex items-center justify-between rounded-md px-3 py-1.5 hover:bg-navy-50"><span>Declaration</span>@if ($form->value('_declared_at'))<span class="text-navy-700" aria-label="done">✓</span>@endif</a></li>
            </ul>
        </nav>

        <form method="POST" action="{{ $plan ? $ctx->route('applications.form.update', $plan) : $ctx->route('form.update') }}" novalidate>
            @csrf @method('PUT')
            <fieldset @disabled($locked) class="space-y-5">
                <legend class="sr-only">Application form</legend>
                @foreach ($sections as $key => $section)
                    <section id="{{ $key }}" class="card p-5" aria-labelledby="{{ $key }}-h">
                        <h2 id="{{ $key }}-h" class="text-lg font-bold">{{ $section['title'] }}</h2>
                        @if (isset($section['repeat']))
                            @php($rows = $form->value($key, []))
                            @for ($i = 0; $i < $section['repeat']; $i++)
                                <div class="mt-3 border-t border-line pt-3 first:mt-3 first:border-0 first:pt-0">
                                    <p class="text-sm font-semibold text-muted">Entry {{ $i + 1 }}</p>
                                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                        @foreach ($section['fields'] as $name => $def)
                                            @include('student._form-field', ['id' => "$key-$i-$name", 'field' => "sections[$key][rows][$i][$name]", 'def' => $def, 'value' => old("sections.$key.rows.$i.$name", $rows[$i][$name] ?? null), 'errorKey' => "sections.$key.rows.$i.$name", 'countries' => $countries])
                                        @endforeach
                                    </div>
                                </div>
                            @endfor
                        @else
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach ($section['fields'] as $name => $def)
                                    @include('student._form-field', ['id' => "$key-$name", 'field' => "sections[$key][$name]", 'def' => $def, 'value' => old("sections.$key.$name", $form->value($name)), 'errorKey' => "sections.$key.$name", 'countries' => $countries])
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endforeach

                <section id="declaration" class="card p-5" aria-labelledby="decl-h">
                    <h2 id="decl-h" class="text-lg font-bold">Declaration</h2>
                    <label class="mt-3 flex items-start gap-2 text-sm"><input type="checkbox" name="declared" value="1" class="mt-0.5 rounded border-slate-300 text-navy-700" @checked(old('declared', (bool) $form->value('_declared_at')))> <span>{{ config('application_form.declaration') }}</span></label>
                    @if ($form->value('_declared_at'))<p class="mt-2 text-xs text-muted">Recorded on {{ \Illuminate\Support\Carbon::parse($form->value('_declared_at'))->format('j M Y') }}.</p>@endif
                </section>
            </fieldset>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                @if ($plan)<a class="btn-outline" href="{{ $ctx->route('applications.show', $plan) }}">Previous</a>@else<span></span>@endif
                <div class="flex flex-wrap gap-2">
                    @unless ($locked)<button class="btn-primary">Save</button>@endunless
                    @if ($plan)<a class="btn-outline" href="{{ $ctx->route('applications.materials', $plan) }}">Next: upload materials</a>@endif
                </div>
            </div>
            @if ($plan && ! $locked)<p class="mt-2 text-right text-xs text-muted">Press Save before you go to the next step.</p>@endif
        </form>
    </div>
</x-dynamic-component>
