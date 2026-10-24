@php($sections = config('application_form.sections'))
<div style="font-size:.875rem;line-height:1.4">
    <p style="color:#6b7280">Read it only to prepare this application.</p>
    @if ($form && $form->data)
        @foreach ($sections as $key => $section)
            @php($rows = isset($section['repeat']) ? ($form->value($key, [])) : [collect($section['fields'])->keys()->mapWithKeys(fn ($n) => [$n => $form->value($n)])->all()])
            @php($rows = collect($rows)->filter(fn ($r) => collect($r)->filter(fn ($v) => filled($v))->isNotEmpty()))
            @continue($rows->isEmpty())
            <h3 style="margin:1rem 0 .25rem;font-weight:600">{{ $section['title'] }}</h3>
            @foreach ($rows as $row)
                <dl style="display:grid;grid-template-columns:minmax(10rem,14rem) 1fr;gap:.25rem .75rem;margin:0 0 .5rem;padding-bottom:.5rem;border-bottom:1px solid #e5e7eb">
                    @foreach ($section['fields'] as $name => $def)
                        @if (filled($row[$name] ?? null))
                            <dt style="color:#6b7280">{{ $def[0] }}</dt><dd style="margin:0;white-space:pre-line">{{ $row[$name] }}</dd>
                        @endif
                    @endforeach
                </dl>
            @endforeach
        @endforeach
        <p style="margin-top:1rem;color:#6b7280">Declaration: {{ $form->value('_declared_at') ? 'signed on '.\Illuminate\Support\Carbon::parse($form->value('_declared_at'))->format('j M Y') : 'not signed yet' }}</p>
    @else
        <p style="margin-top:.75rem">The student has not filled in the form yet.</p>
    @endif
</div>
