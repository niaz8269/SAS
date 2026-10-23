<x-layouts.partner title="Batch import">
    <p class="text-sm"><a class="text-navy-700 underline" href="{{ route('partner.students.index') }}">&larr; Student list</a></p>
    <h1 class="mt-2 text-2xl font-extrabold">Batch import</h1>
    <p class="mt-1 text-sm text-muted">Add many students at once from a CSV file (up to 200). Each student is emailed an invitation to choose their own password.</p>

    <section class="card mt-5 max-w-3xl p-5">
        <h2 class="font-bold">1. Get the template</h2>
        <p class="mt-1 text-sm text-muted">Columns: <code>surname, name, email, country, passport_number, date_of_birth</code>. The country must be spelled as in the Add student form. Dates are written YYYY-MM-DD.</p>
        <a class="btn-outline mt-3" href="{{ route('partner.students.template') }}">Download the template</a>

        <h2 class="mt-6 font-bold">2. Upload your file</h2>
        <form method="POST" action="{{ route('partner.students.import.store') }}" enctype="multipart/form-data" class="mt-2 space-y-3">
            @csrf
            <div><label for="file" class="label">CSV file</label><input id="file" type="file" name="file" required accept=".csv,text/csv,text/plain" class="input @error('file') input-error @enderror">@error('file')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-slate-300 text-navy-700"> <span>I confirm these students asked me to apply for them and agreed that SAS processes their personal data for their applications.</span></label>
            @error('consent')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            <button class="btn-primary">Import</button>
        </form>
    </section>

    @if (session('skipped'))
        <section class="card mt-5 max-w-3xl border-warn/40 p-5" role="alert">
            <h2 class="font-bold">These lines were not added</h2>
            <ul class="mt-2 list-disc pl-5 text-sm">@foreach (session('skipped') as $line => $why)<li>Line {{ $line }}: {{ $why }}</li>@endforeach</ul>
        </section>
    @endif
</x-layouts.partner>
