<x-layouts.student title="Privacy and data">
    <h1 class="text-2xl font-extrabold">Privacy and your data</h1>

    <section class="card mt-5 p-5" aria-labelledby="exp"><h2 id="exp" class="text-lg font-bold">Download your data</h2>
        <p class="mt-1 text-sm text-muted">A JSON file with your account, profile, plans, saved scholarships, document details (not the files), preferences and enquiries.</p>
        <form method="POST" action="{{ route('student.privacy.export') }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf
            <div><label for="pw-e" class="label">Confirm your password</label><x-password-input id="pw-e" name="password" autocomplete="current-password" :invalid="$errors->has('password')" /></div>
            <button class="btn-outline">Download</button></form>
    </section>

    @if (auth()->user()->managed_by_organisation_id && auth()->user()->managedBy)
        <section class="card mt-5 p-5" aria-labelledby="agency"><h2 id="agency" class="text-lg font-bold">Your agency</h2>
            <p class="mt-1 text-sm text-muted"><strong>{{ auth()->user()->managedBy->name }}</strong> set up this account for you and can see and work on your application form, applications and documents. You can end that at any time. Your account, documents and applications stay yours; only the agency's access ends.</p>
            <form method="POST" action="{{ route('student.privacy.agency.stop') }}" class="mt-3 flex flex-wrap items-end gap-2" onsubmit="return confirm('Stop {{ addslashes(auth()->user()->managedBy->name) }} from seeing and working on your applications?')">@csrf
                <div><label for="pw-a" class="label">Confirm your password</label><x-password-input id="pw-a" name="password" autocomplete="current-password" /></div>
                <button class="btn-danger">Stop my agency's access</button></form>
        </section>
    @endif

    <section class="card mt-5 border-alert/40 p-5" aria-labelledby="del"><h2 id="del" class="text-lg font-bold">Delete your account</h2>
        @if ($blocked)
            <p class="mt-2 text-sm">{{ $blocked }}</p>
        @else
            <p class="mt-1 text-sm text-muted">Deletes your profile, plans, saved scholarships, preferences and every uploaded document. Past enquiries are kept without your name or contact details. Backups expire on their normal schedule. We email a confirmation link before anything is deleted.</p>
            <form method="POST" action="{{ route('student.privacy.delete.request') }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf
                <div><label for="pw-d" class="label">Confirm your password</label><x-password-input id="pw-d" name="password" autocomplete="current-password" /></div>
                <button class="btn-danger">Email me a confirmation link</button></form>
        @endif
        @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </section>
</x-layouts.student>
