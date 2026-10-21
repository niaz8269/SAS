<x-layouts.auth title="Verify your email">
    <p class="text-sm text-muted">We sent a verification link to <strong class="text-ink">{{ auth()->user()->email }}</strong>. Verify your email to use your workspace.</p>
    <form method="POST" action="{{ route('verification.send') }}" class="mt-5">@csrf<button class="btn-primary w-full">Resend verification email</button></form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="btn-ghost w-full">Log out</button></form>
</x-layouts.auth>