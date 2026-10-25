<x-layouts.student title="Security">
    <h1 class="text-2xl font-extrabold">Security</h1>
    <p class="mt-1 text-sm text-muted">Two-step verification adds a code from an authenticator app to your password. It is optional for students.</p>

    @if ($recoveryCodes)
        <div class="card mt-5 border-warn/40 bg-warn-soft p-5" role="alert">
            <h2 class="font-bold">Save your recovery codes</h2>
            <p class="mt-1 text-sm">Each code works once if you lose your phone. They are shown only now.</p>
            <ul class="mt-3 grid grid-cols-2 gap-1 tabular-nums tracking-wider text-sm">@foreach ($recoveryCodes as $c)<li>{{ $c }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($enabled)
        <section class="card mt-5 p-5"><p><span class="badge-green">On</span> Your account asks for a code when you log in.</p>
            <form method="POST" action="{{ route('student.security.recovery') }}" class="mt-4 flex flex-wrap items-end gap-2">@csrf
                <div><label for="pw1" class="label">Password</label><x-password-input id="pw1" name="password" autocomplete="current-password" /></div>
                <button class="btn-outline">New recovery codes</button></form>
            @unless (auth()->user()->isStaff())
                <form method="POST" action="{{ route('student.security.disable') }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf
                    <div><label for="pw2" class="label">Password</label><x-password-input id="pw2" name="password" autocomplete="current-password" /></div>
                    <button class="btn-danger">Turn off</button></form>
            @endunless
            @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </section>
    @else
        <section class="card mt-5 p-5">
            <h2 class="text-lg font-bold">Turn on two-step verification</h2>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm"><li>Scan this code with an authenticator app, or type the key.</li><li>Enter the 6-digit code it shows.</li></ol>
            <img src="{{ $qr }}" alt="QR code to scan with your authenticator app" width="176" height="176" class="mt-3 h-44 w-44 max-w-full">
            <p class="mt-2 text-sm">Key: <code class="tabular-nums tracking-wider">{{ $secret }}</code></p>
            <form method="POST" action="{{ route('student.security.enable') }}" class="mt-4 flex items-end gap-2">@csrf
                <div><label for="code" class="label">Code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required class="input @error('code') input-error @enderror">@error('code')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                <button class="btn-primary">Turn on</button></form>
        </section>
    @endif
</x-layouts.student>
