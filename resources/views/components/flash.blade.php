@if (session('status') || session('error'))
    <div class="container-page mt-4" role="status" aria-live="polite">
        @if (session('status'))
            <div class="rounded-md border border-forest-200 bg-forest-50 px-4 py-3 text-sm text-forest-900">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md border border-alert bg-alert-soft px-4 py-3 text-sm text-alert" role="alert">{{ session('error') }}</div>
        @endif
    </div>
@endif