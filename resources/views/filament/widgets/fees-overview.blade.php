<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Fees and payments</x-slot>
        <x-slot name="afterHeader"><span style="color:var(--sas-muted);font-size:.85rem">{{ $percent }}% collected</span></x-slot>
        @if ($count === 0)
            <p style="color:var(--sas-muted);font-size:.9rem">No invoices yet. Fees you bill to students appear here.</p>
        @else
            <span style="display:block;height:.7rem;border-radius:999px;background:#eef1f7;overflow:hidden"><span style="display:block;height:100%;width:{{ $percent }}%;background:#1a8f4c;border-radius:999px"></span></span>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:1.1rem">
                <div><p style="font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;color:var(--sas-muted);font-weight:600">Billed</p><p style="margin-top:.2rem;font-size:1.15rem;font-weight:700">{{ $billed }}</p></div>
                <div><p style="font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;color:var(--sas-muted);font-weight:600">Collected</p><p style="margin-top:.2rem;font-size:1.15rem;font-weight:700;color:#1a8f4c">{{ $collected }}</p></div>
                <div><p style="font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;color:var(--sas-muted);font-weight:600">Outstanding</p><p style="margin-top:.2rem;font-size:1.15rem;font-weight:700;color:#b45309">{{ $outstanding }}</p></div>
            </div>
            <p style="margin-top:1rem"><a href="{{ $url }}" style="color:var(--sas-navy);font-weight:500;font-size:.875rem;text-decoration:none">View invoices &rarr;</a></p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
