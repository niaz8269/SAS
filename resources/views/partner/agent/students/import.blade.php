<x-layouts.partner title="Batch import" crumb="Student list › Batch import">
    <div class="flex items-start justify-between gap-3">
        <div><p class="text-[0.8125rem]">Backend home</p><h1 class="text-[1.5rem] font-light leading-tight text-[#3a87ad]">Batch import</h1></div>
        <a class="inline-flex items-center gap-2 bg-[#d9534f] px-5 py-2.5 text-[0.875rem] text-white hover:bg-[#c9453f]" href="{{ route('partner.students.index') }}"><span aria-hidden="true">↩</span> return</a>
    </div>
    <div class="mt-2 border-b border-[#e5e5e5]"></div>
    <p class="mt-4 max-w-3xl text-[0.8125rem] text-[#555]">Add many students at once from a CSV file (up to 200). Each student is emailed an invitation to choose their own password.</p>

    <div class="mt-5 max-w-3xl space-y-6">
        <section>
            <h2 class="text-[0.875rem] font-semibold">1. Get the template</h2>
            <p class="mt-1 text-[0.8125rem] text-[#555]">Columns: <code>surname, name, email, country, passport_number, date_of_birth</code>. The country must be spelled as in the Add students form. Dates are written YYYY-MM-DD.</p>
            <a class="mt-3 inline-flex items-center gap-2 bg-[#86b87c] px-6 py-2.5 text-[0.875rem] text-white hover:bg-[#78aa6e]" href="{{ route('partner.students.template') }}">Download the template</a>
        </section>
        <section>
            <h2 class="text-[0.875rem] font-semibold">2. Upload your file</h2>
            <form method="POST" action="{{ route('partner.students.import.store') }}" enctype="multipart/form-data" class="mt-2 space-y-3">
                @csrf
                <div><label for="file" class="mb-1 block text-[0.8125rem]">CSV file</label><input id="file" type="file" name="file" required accept=".csv,text/csv,text/plain" class="block w-full max-w-lg border border-[#ccc] px-3 py-2 @error('file') border-[#d9534f] @enderror">@error('file')<p class="mt-1 text-sm text-[#d9534f]" role="alert">{{ $message }}</p>@enderror</div>
                <label class="flex max-w-lg items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-0.5"> <span>I confirm these students asked me to apply for them and agreed that SAS processes their personal data for their applications.</span></label>
                @error('consent')<p class="text-sm text-[#d9534f]" role="alert">{{ $message }}</p>@enderror
                <button class="inline-flex items-center gap-2 bg-[#6aaad8] px-7 py-3 text-[0.875rem] text-white hover:bg-[#5b9bc9]"><span aria-hidden="true">✔</span> submit</button>
            </form>
        </section>
        @if (session('skipped'))
            <section class="border border-[#e0a800] bg-[#fff8e1] p-4" role="alert">
                <h2 class="font-semibold">These lines were not added</h2>
                <ul class="mt-2 list-disc pl-5 text-sm">@foreach (session('skipped') as $line => $why)<li>Line {{ $line }}: {{ $why }}</li>@endforeach</ul>
            </section>
        @endif
    </div>
</x-layouts.partner>
