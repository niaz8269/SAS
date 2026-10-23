<x-layouts.student title="Application form">
    <h1 class="text-2xl font-extrabold">Application form</h1>
    <p class="mt-1 text-sm text-muted">Fill this in once. Scholarship applications use these answers, so you do not retype them for each one. The form follows the China Scholarship Council application form. Every answer is stored encrypted and is private to you. Your counsellor can read it only after you share it on a case.</p>

    <section class="card mt-5 p-5" aria-labelledby="done-h">
        <h2 id="done-h" class="font-bold">{{ $percent }}% of the required answers are filled in</h2>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-ivory-deep" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="Form progress"><div class="h-full bg-navy-600" style="width: {{ $percent }}%"></div></div>
        @if ($missing)
            <p class="mt-3 text-sm text-muted">Still needed: {{ implode(', ', $missing) }}.</p>
        @else
            <p class="mt-3 text-sm">Everything required is filled in.</p>
        @endif
        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
            @foreach ($sections as $key => $section)<li><a class="text-navy-700 underline" href="#{{ $key }}">{{ $section['title'] }}</a></li>@endforeach
            <li><a class="text-navy-700 underline" href="#declaration">Declaration</a></li>
        </ul>
    </section>

    @foreach ($sections as $key => $section)
        <section id="{{ $key }}" class="card mt-5 p-5" aria-labelledby="{{ $key }}-h">
            <h2 id="{{ $key }}-h" class="text-lg font-bold">{{ $section['title'] }}</h2>
            <form method="POST" action="{{ route('student.form.update', $key) }}" class="mt-3">
                @csrf @method('PUT')
                @if (isset($section['repeat']))
                    @php($rows = $form->value($key, []))
                    @for ($i = 0; $i < $section['repeat']; $i++)
                        <fieldset class="mt-3 border-t border-line pt-3 first:mt-0 first:border-0 first:pt-0">
                            <legend class="text-sm font-semibold text-muted">Entry {{ $i + 1 }}</legend>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                @foreach ($section['fields'] as $name => $def)
                                    @include('student._form-field', ['id' => "$key-$i-$name", 'field' => "rows[$i][$name]", 'def' => $def, 'value' => old("rows.$i.$name", $rows[$i][$name] ?? null), 'errorKey' => "rows.$i.$name", 'countries' => $countries])
                                @endforeach
                            </div>
                        </fieldset>
                    @endfor
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($section['fields'] as $name => $def)
                            @include('student._form-field', ['id' => "$key-$name", 'field' => $name, 'def' => $def, 'value' => old($name, $form->value($name)), 'errorKey' => $name, 'countries' => $countries])
                        @endforeach
                    </div>
                @endif
                <button class="btn-primary mt-4">Save {{ \Illuminate\Support\Str::lower($section['title']) }}</button>
            </form>
        </section>
    @endforeach

    <section id="declaration" class="card mt-5 p-5" aria-labelledby="decl-h">
        <h2 id="decl-h" class="text-lg font-bold">Declaration</h2>
        <form method="POST" action="{{ route('student.form.declare') }}" class="mt-3">
            @csrf
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="agreed" value="1" class="mt-0.5 rounded border-slate-300 text-navy-700" @checked($form->value('_declared_at'))> <span>{{ config('application_form.declaration') }}</span></label>
            @if ($form->value('_declared_at'))<p class="mt-2 text-xs text-muted">Recorded on {{ \Illuminate\Support\Carbon::parse($form->value('_declared_at'))->format('j M Y') }}. Untick and save to withdraw it.</p>@endif
            <button class="btn-primary mt-3">Save declaration</button>
        </form>
    </section>
</x-layouts.student>
