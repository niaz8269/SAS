<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Application form {{ $plan->application_no }} | {{ config('sas.brand.short') }}</title>
    <style>
        body { font-family: Inter, Arial, sans-serif; color: #111827; margin: 0; padding: 24px; line-height: 1.45; }
        .page { max-width: 820px; margin: 0 auto; }
        h1 { font-size: 22px; margin: 0 0 4px; } h2 { font-size: 16px; margin: 22px 0 6px; padding-bottom: 4px; border-bottom: 2px solid #123a7a; color: #123a7a; }
        dl { display: grid; grid-template-columns: 230px 1fr; gap: 4px 14px; margin: 0 0 8px; font-size: 14px; } dt { color: #4b5563; } dd { margin: 0; white-space: pre-line; }
        .entry { border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 8px; }
        .bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; font-size: 13px; color: #4b5563; }
        button { background: #123a7a; color: #fff; border: 0; border-radius: 8px; padding: 8px 14px; font-weight: 600; cursor: pointer; }
        ul { padding-left: 18px; font-size: 14px; }
        @media print { .bar button { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<div class="page">
    <div class="bar"><span>Generated {{ now()->format('j M Y H:i') }} &middot; {{ config('sas.brand.short') }}</span><button type="button" onclick="window.print()">Print or save as PDF</button></div>
    <h1>Application form</h1>
    <dl>
        <dt>Application number</dt><dd>{{ $plan->application_no }}</dd>
        <dt>Applicant</dt><dd>{{ $plan->user->name }}</dd>
        <dt>Scholarship</dt><dd>{{ $plan->cycle->displayName() }}</dd>
        <dt>Degree</dt><dd>{{ $plan->degreeLevel?->name ?? 'Not chosen' }}</dd>
        <dt>Language of study</dt><dd>{{ $plan->study_language ?? 'Not chosen' }}</dd>
        <dt>Status</dt><dd>{{ $plan->stageLabel() }}</dd>
    </dl>

    @foreach ($sections as $key => $section)
        @php($entries = isset($section['repeat']) ? collect($form->value($key, [])) : collect([collect($section['fields'])->keys()->mapWithKeys(fn ($n) => [$n => $form->value($n)])->all()]))
        @php($entries = $entries->filter(fn ($e) => collect($e)->filter(fn ($v) => filled($v))->isNotEmpty()))
        @continue($entries->isEmpty())
        <h2>{{ $section['title'] }}</h2>
        @foreach ($entries as $entry)
            <dl class="entry">
                @foreach ($section['fields'] as $name => $def)@if (filled($entry[$name] ?? null))<dt>{{ $def[0] }}</dt><dd>{{ $entry[$name] }}</dd>@endif @endforeach
            </dl>
        @endforeach
    @endforeach

    <h2>Documents</h2>
    @if ($rows->isEmpty())<p style="font-size:14px">No documents uploaded yet.</p>@else
        <ul>@foreach ($rows as $r)<li><strong>{{ $r['kind']->name }}</strong>: @if ($r['documents']->isNotEmpty()){{ $r['documents']->map(fn ($d) => $d->currentVersion?->original_name ?? $d->title)->implode(', ') }}@else cannot provide now: {{ $r['reason'] }}@endif</li>@endforeach</ul>
    @endif

    <h2>Declaration</h2>
    <p style="font-size:14px">{{ config('application_form.declaration') }}<br>{{ $form->value('_declared_at') ? 'Declared on '.\Illuminate\Support\Carbon::parse($form->value('_declared_at'))->format('j M Y') : 'Not declared yet.' }}</p>
</div>
</body>
</html>
