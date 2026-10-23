@php($type = $def[1])
<div class="{{ $type === 'textarea' ? 'sm:col-span-2' : '' }}">
    <label for="{{ $id }}" class="label">{{ $def[0] }}@if ($def['required'] ?? false) <span class="text-alert" aria-hidden="true">*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $field }}" rows="2" maxlength="600" class="input @error($errorKey) input-error @enderror">{{ $value }}</textarea>
    @elseif ($type === 'select' || $type === 'yesno' || $type === 'country')
        @php($options = match ($type) { 'yesno' => ['yes' => 'Yes', 'no' => 'No'], 'country' => $countries->mapWithKeys(fn ($c) => [$c => $c])->all(), default => array_combine($def['options'], $def['options']) })
        <select id="{{ $id }}" name="{{ $field }}" class="input @error($errorKey) input-error @enderror"><option value="">Not answered</option>@foreach ($options as $k => $l)<option value="{{ $k }}" @selected((string) $value === (string) $k)>{{ $l }}</option>@endforeach</select>
    @else
        <input id="{{ $id }}" name="{{ $field }}" type="{{ in_array($type, ['date', 'email']) ? $type : 'text' }}" value="{{ $value }}" maxlength="190" class="input @error($errorKey) input-error @enderror" @if ($def['required'] ?? false) aria-required="true" @endif>
    @endif
    @error($errorKey)<p class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
