<x-layouts.partner title="Backend home">
    <h1 class="text-2xl font-extrabold">Backend home</h1>
    <p class="mt-1 text-sm text-muted">{{ $organisation->name }}. Add your students, fill in their applications with them, and follow what SAS says about each one.</p>

    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <a href="{{ route('partner.applications.index') }}" class="card block p-5 transition hover:border-navy-500"><p class="text-4xl font-extrabold">{{ $applications }}</p><p class="text-sm text-muted">Number of applications</p></a>
        <a href="{{ route('partner.students.index') }}" class="card block p-5 transition hover:border-navy-500"><p class="text-4xl font-extrabold">{{ $students }}</p><p class="text-sm text-muted">Number of students</p></a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card p-5" aria-labelledby="stage-h"><h2 id="stage-h" class="font-bold">Applications by status</h2>
            <ul class="mt-3 divide-y divide-line text-sm">
                @foreach (\App\Models\ApplicationPlan::STAGES as $key => $label)
                    <li class="flex items-center justify-between py-2"><a class="hover:underline" href="{{ route('partner.applications.index', ['stage' => $key]) }}">{{ $label }}</a><span class="font-semibold">{{ $byStage[$key] ?? 0 }}</span></li>
                @endforeach
            </ul>
        </section>
        <section class="card p-5" aria-labelledby="start-h"><h2 id="start-h" class="font-bold">What to do</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-muted">
                <li><strong class="text-ink">Add students</strong>, one at a time or many from a file. Each student is emailed an invitation to choose their own password.</li>
                <li>Open a student and press <strong class="text-ink">Add application</strong> to choose a scholarship.</li>
                <li>Follow the four steps: apply, application form, upload materials, submit.</li>
                <li>SAS reviews each submitted application and either passes it or returns it with remarks for you to fix.</li>
            </ol>
            <div class="mt-4 flex flex-wrap gap-2"><a class="btn-primary" href="{{ route('partner.students.create') }}">Add a student</a><a class="btn-outline" href="{{ route('partner.students.import') }}">Batch import</a></div>
        </section>
    </div>
</x-layouts.partner>
