@php($editing = $student !== null)
@php($personal = $editing ? $form->data : [])
<x-layouts.partner :title="$editing ? 'Edit student' : 'Add student'">
    <p class="text-sm"><a class="text-navy-700 underline" href="{{ $editing ? route('partner.student.show', $student->public_id) : route('partner.students.index') }}">&larr; {{ $editing ? $student->name : 'Student list' }}</a></p>
    <h1 class="mt-2 text-2xl font-extrabold">{{ $editing ? 'Edit student' : 'Add students' }}</h1>
    @unless ($editing)<p class="mt-1 text-sm text-muted">We email the student an invitation to choose their own password. You can start their applications straight away.</p>@endunless

    <form method="POST" action="{{ $editing ? route('partner.student.update', $student->public_id) : route('partner.students.store') }}" class="card mt-5 grid max-w-3xl gap-4 p-5 sm:grid-cols-2" novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        @php($fields = [
            ['surname', 'Surname', 'text', $personal['surname'] ?? null], ['name', 'Given name', 'text', $personal['given_name'] ?? null],
            ['email', 'Account (email)', 'email', $student?->email], ['passport_number', 'Passport number', 'text', $personal['passport_no'] ?? null],
        ])
        @foreach ($fields as [$name, $label, $type, $current])
            <div><label for="f-{{ $name }}" class="label">{{ $label }}@if ($name !== 'passport_number') <span class="text-alert" aria-hidden="true">*</span>@endif</label>
                <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $current) }}" maxlength="190" class="input @error($name) input-error @enderror">@error($name)<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
        @endforeach
        <div><label for="f-country" class="label">Country of citizenship <span class="text-alert" aria-hidden="true">*</span></label>
            <select id="f-country" name="country" class="input @error('country') input-error @enderror"><option value="">Please select</option>@foreach ($countries as $c)<option value="{{ $c }}" @selected(old('country', $personal['nationality'] ?? null) === $c)>{{ $c }}</option>@endforeach</select>@error('country')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
        <div><label for="f-dob" class="label">Date of birth <span class="text-alert" aria-hidden="true">*</span></label>
            <input id="f-dob" name="date_of_birth" type="date" value="{{ old('date_of_birth', $personal['date_of_birth'] ?? null) }}" class="input @error('date_of_birth') input-error @enderror">@error('date_of_birth')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
        @unless ($editing)
            <div class="sm:col-span-2"><label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-slate-300 text-navy-700" @checked(old('consent'))> <span>{{ $consent }}</span></label>@error('consent')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
        @endunless
        <div class="sm:col-span-2 flex gap-2"><button class="btn-primary">Submit</button><a class="btn-outline" href="{{ $editing ? route('partner.student.show', $student->public_id) : route('partner.students.index') }}">Cancel</a></div>
    </form>
</x-layouts.partner>
