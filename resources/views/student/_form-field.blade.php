@php($type = $def[1])
@php($box = 'w-full rounded-lg border bg-white px-3 py-2 text-base disabled:bg-slate-50 disabled:opacity-80 '.($errors->has($errorKey) ? 'border-alert' : 'border-slate-300'))
<div class="flex flex-col gap-1 {{ $type === 'textarea' ? 'lg:col-span-2' : '' }}">
    <label for="{{ $id }}" class="text-sm font-medium text-ink">@if ($def['required'] ?? false)<span class="text-alert" aria-hidden="true">*</span> @endif{{ $def[0] }}@if ($def['required'] ?? false)<span class="sr-only"> (required)</span>@endif</label>
    <div class="min-w-0">
        @if ($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $field }}" rows="3" maxlength="600" class="{{ $box }}">{{ $value }}</textarea>
        @elseif ($type === 'select' || $type === 'yesno' || $type === 'country')
            @php($options = match ($type) { 'yesno' => ['yes' => 'Yes', 'no' => 'No'], 'country' => $countries->mapWithKeys(fn ($c) => [$c => $c])->all(), default => array_combine($def['options'], $def['options']) })
            @if ($type === 'yesno')
                <div class="flex gap-6 pt-1 text-base">@foreach ($options as $k => $l)<label class="inline-flex items-center gap-2"><input type="radio" name="{{ $field }}" value="{{ $k }}" class="h-4 w-4" @checked((string) $value === (string) $k)> {{ $l }}</label>@endforeach</div>
            @else
                <select id="{{ $id }}" name="{{ $field }}" class="{{ $box }}"><option value="">Not answered</option>@foreach ($options as $k => $l)<option value="{{ $k }}" @selected((string) $value === (string) $k)>{{ $l }}</option>@endforeach</select>
            @endif
        @else
            <input id="{{ $id }}" name="{{ $field }}" type="{{ in_array($type, ['date', 'email']) ? $type : 'text' }}" value="{{ $value }}" maxlength="190" class="{{ $box }}" @if ($def['required'] ?? false) aria-required="true" @endif>
        @endif
        @error($errorKey)<p class="mt-1 text-sm text-alert" role="alert">{{ $message }}</p>@enderror
    </div>
</div>
