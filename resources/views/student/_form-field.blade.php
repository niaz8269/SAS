@php($type = $def[1])
<div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:gap-3 {{ $type === 'textarea' ? 'lg:col-span-2' : '' }}">
    <label for="{{ $id }}" class="w-full text-[0.8125rem] text-[#444] sm:w-40 sm:shrink-0 sm:pt-1.5 sm:text-right">@if ($def['required'] ?? false)<span class="text-[#d9534f]" aria-hidden="true">*</span> @endif{{ $def[0] }}</label>
    <div class="min-w-0 flex-1">
        @if ($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $field }}" rows="2" maxlength="600" class="w-full border border-[#ccc] bg-[#f9f9f9] px-2 py-1.5 text-[0.875rem] disabled:opacity-70 @error($errorKey) border-[#d9534f] @enderror">{{ $value }}</textarea>
        @elseif ($type === 'select' || $type === 'yesno' || $type === 'country')
            @php($options = match ($type) { 'yesno' => ['yes' => 'Yes', 'no' => 'No'], 'country' => $countries->mapWithKeys(fn ($c) => [$c => $c])->all(), default => array_combine($def['options'], $def['options']) })
            @if ($type === 'yesno')
                <div class="flex gap-4 pt-1.5 text-[0.875rem]">@foreach ($options as $k => $l)<label class="inline-flex items-center gap-1.5"><input type="radio" name="{{ $field }}" value="{{ $k }}" @checked((string) $value === (string) $k)> {{ $l }}</label>@endforeach</div>
            @else
                <select id="{{ $id }}" name="{{ $field }}" class="w-full border border-[#ccc] bg-[#f9f9f9] px-2 py-1.5 text-[0.875rem] disabled:opacity-70 @error($errorKey) border-[#d9534f] @enderror"><option value="">Not answered</option>@foreach ($options as $k => $l)<option value="{{ $k }}" @selected((string) $value === (string) $k)>{{ $l }}</option>@endforeach</select>
            @endif
        @else
            <input id="{{ $id }}" name="{{ $field }}" type="{{ in_array($type, ['date', 'email']) ? $type : 'text' }}" value="{{ $value }}" maxlength="190" class="w-full border border-[#ccc] bg-[#f9f9f9] px-2 py-1.5 text-[0.875rem] disabled:opacity-70 @error($errorKey) border-[#d9534f] @enderror" @if ($def['required'] ?? false) aria-required="true" @endif>
        @endif
        @error($errorKey)<p class="mt-1 text-[0.8125rem] text-[#d9534f]" role="alert">{{ $message }}</p>@enderror
    </div>
</div>
