@php($options = \App\Models\PaymentOption::available()->get())
<div class="my-4">
    <h3 class="font-semibold">How to pay</h3>
    @if ($options->isEmpty())
        <p class="mt-1 text-sm">We confirm how to pay in writing when you enquire.</p>
    @else
        <ul class="mt-2 space-y-3 text-sm">
            @foreach ($options as $o)
                <li class="rounded-lg border border-line bg-white p-3">
                    <p class="font-semibold">{{ $o->name }} <span class="badge-neutral ml-1 align-middle">{{ \App\Models\PaymentOption::KINDS[$o->kind] ?? $o->kind }}</span></p>
                    @if ($o->instructions)<p class="mt-1 whitespace-pre-line text-muted">{{ $o->instructions }}</p>@endif
                    @if ($o->kind === 'online' && $o->link_url)<p class="mt-2"><a class="btn-primary" rel="noopener" target="_blank" href="{{ \App\Domain\Site\Site::href($o->link_url) }}">Pay online</a></p>@endif
                </li>
            @endforeach
        </ul>
    @endif
</div>