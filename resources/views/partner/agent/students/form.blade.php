@php($editing = $student !== null)
@php($personal = $editing ? $form->data : [])
<x-layouts.partner :title="$editing ? 'Edit student' : 'Add students'" :crumb="$editing ? 'Student list › Edit' : 'Student list › Add to'">
    <div class="flex items-start justify-between gap-3">
        <div><p class="text-[0.8125rem]">Backend home</p><h1 class="text-[1.5rem] font-light leading-tight text-[#3a87ad]">{{ $editing ? 'Edit student' : 'Add students' }}</h1></div>
        <a class="inline-flex items-center gap-2 bg-[#d9534f] px-5 py-2.5 text-[0.875rem] text-white hover:bg-[#c9453f]" href="{{ $editing ? route('partner.student.show', $student->public_id) : route('partner.students.index') }}"><span aria-hidden="true">↩</span> return</a>
    </div>
    <div class="mt-2 border-b border-[#e5e5e5]"></div>
    @unless ($editing)<p class="mt-4 text-[0.8125rem] text-[#555]">We email the student an invitation to choose their own password. You can start their applications straight away.</p>@endunless

    <form method="POST" action="{{ $editing ? route('partner.student.update', $student->public_id) : route('partner.students.store') }}" class="mt-6 max-w-3xl space-y-4" novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        @php($fields = [
            ['surname', 'surname', 'text', $personal['surname'] ?? null, true], ['name', 'name', 'text', $personal['given_name'] ?? null, true],
            ['email', 'Account (email)', 'email', $student?->email, true], ['passport_number', 'Passport number', 'text', $personal['passport_no'] ?? null, false],
        ])
        @foreach ($fields as [$name, $label, $type, $current, $required])
            <div class="grid items-start gap-2 sm:grid-cols-[13.125rem_1fr] sm:gap-4"><label for="f-{{ $name }}" class="pt-2 sm:text-right">@if ($required)<span class="text-[#d9534f]" aria-hidden="true">*</span> @endif{{ $label }}:</label>
                <div><input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $current) }}" maxlength="190" class="w-full max-w-lg border border-[#ccc] px-3 py-2 @error($name) border-[#d9534f] @enderror">@error($name)<p class="mt-1 text-sm text-[#d9534f]" role="alert">{{ $message }}</p>@enderror</div></div>
        @endforeach
        <div class="grid items-start gap-2 sm:grid-cols-[13.125rem_1fr] sm:gap-4"><label for="f-country" class="pt-2 sm:text-right"><span class="text-[#d9534f]" aria-hidden="true">*</span> Country of Citizenship:</label>
            <div><select id="f-country" name="country" class="w-full max-w-lg border border-[#ccc] bg-white px-3 py-2 @error('country') border-[#d9534f] @enderror"><option value="">Please select</option>@foreach ($countries as $c)<option value="{{ $c }}" @selected(old('country', $personal['nationality'] ?? null) === $c)>{{ $c }}</option>@endforeach</select>@error('country')<p class="mt-1 text-sm text-[#d9534f]" role="alert">{{ $message }}</p>@enderror</div></div>
        <div class="grid items-start gap-2 sm:grid-cols-[13.125rem_1fr] sm:gap-4"><label for="f-dob" class="pt-2 sm:text-right"><span class="text-[#d9534f]" aria-hidden="true">*</span> Date of birth:</label>
            <div><input id="f-dob" name="date_of_birth" type="date" value="{{ old('date_of_birth', $personal['date_of_birth'] ?? null) }}" class="w-full max-w-lg border border-[#ccc] px-3 py-2 @error('date_of_birth') border-[#d9534f] @enderror">@error('date_of_birth')<p class="mt-1 text-sm text-[#d9534f]" role="alert">{{ $message }}</p>@enderror</div></div>
        @unless ($editing)
            <div class="grid items-start gap-2 sm:grid-cols-[13.125rem_1fr] sm:gap-4"><span></span>
                <div><label class="flex max-w-lg items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-0.5" @checked(old('consent'))> <span>{{ $consent }}</span></label>@error('consent')<p class="mt-1 text-sm text-[#d9534f]" role="alert">{{ $message }}</p>@enderror</div></div>
        @endunless
        <div class="flex gap-2 pt-2 sm:pl-[14.125rem]"><button class="inline-flex items-center gap-2 bg-[#6aaad8] px-7 py-3 text-[0.875rem] text-white hover:bg-[#5b9bc9]"><span aria-hidden="true">✔</span> submit</button><button type="reset" class="inline-flex items-center gap-2 bg-[#a5adb3] px-7 py-3 text-[0.875rem] text-white hover:bg-[#939ba1]"><span aria-hidden="true">↺</span> Reset</button></div>
    </form>
</x-layouts.partner>
