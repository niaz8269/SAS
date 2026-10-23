<x-layouts.student title="Your details">
    <p class="text-sm font-semibold uppercase tracking-wide text-navy-700">Step 1 of 3</p>
    <h1 class="text-2xl font-extrabold">Your details</h1>
    <p class="mt-1 text-sm text-muted">Fill this in <strong>once</strong>. Every scholarship you apply for uses these answers. Open a section, answer, and press Save. You can stop and come back at any time. Your answers are private and stored encrypted. Your counsellor sees them only if you share them.</p>

    <section class="card mt-5 p-5" aria-labelledby="done-h">
        <h2 id="done-h" class="font-bold">{{ $percent }}% done</h2>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-ivory-deep" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="Form progress"><div class="h-full bg-navy-600" style="width: {{ $percent }}%"></div></div>
        @if ($missing)
            <p class="mt-3 text-sm text-muted">Still to do: {{ collect($progress)->filter(fn ($p) => $p['answered'] < $p['required'])->keys()->map(fn ($k) => $sections[$k]['title'])->when(in_array('Declaration', $missing), fn ($c) => $c->push('Declaration'))->implode(', ') }}.</p>
        @else
            <p class="mt-3 text-sm">✓ Everything required is filled in.</p>
        @endif
        @if ($percent >= 100)<a href="{{ route('student.choose.index') }}" class="btn-primary mt-3">Next: choose scholarships</a>@endif
    </section>

    @php($firstTodo = collect($progress)->filter(fn ($p) => $p['answered'] < $p['required'])->keys()->first())
    @foreach ($sections as $key => $section)
        @php($p = $progress[$key])
        @php($isOpen = session('open') === $key || (! session('open') && $firstTodo === $key))
        <details id="{{ $key }}" class="card mt-3 p-5" @if ($isOpen) open @endif>
            <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 text-lg font-bold">
                <span>{{ $section['title'] }}</span>
                @if ($p['required'] === 0)<span class="badge-neutral">Optional</span>
                @elseif ($p['answered'] >= $p['required'])<span class="badge-green">✓ Done</span>
                @else<span class="badge-warn">{{ $p['required'] - $p['answered'] }} to answer</span>@endif
            </summary>
            <form method="POST" action="{{ route('student.form.update', $key) }}" class="mt-4">
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
                <button class="btn-primary mt-4">Save</button>
            </form>
        </details>
    @endforeach

    <details id="declaration" class="card mt-3 p-5" @if (session('open') === 'declaration' || (! $firstTodo && ! $form->value('_declared_at'))) open @endif>
        <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 text-lg font-bold"><span>Declaration</span>@if ($form->value('_declared_at'))<span class="badge-green">✓ Done</span>@else<span class="badge-warn">To do</span>@endif</summary>
        <form method="POST" action="{{ route('student.form.declare') }}" class="mt-4">
            @csrf
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="agreed" value="1" class="mt-0.5 rounded border-slate-300 text-navy-700" @checked($form->value('_declared_at'))> <span>{{ config('application_form.declaration') }}</span></label>
            @if ($form->value('_declared_at'))<p class="mt-2 text-xs text-muted">Recorded on {{ \Illuminate\Support\Carbon::parse($form->value('_declared_at'))->format('j M Y') }}. Untick and save to withdraw it.</p>@endif
            <button class="btn-primary mt-3">Save declaration</button>
        </form>
    </details>
</x-layouts.student>
