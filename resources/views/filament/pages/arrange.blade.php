<x-filament-panels::page>
    <p class="max-w-4xl text-sm text-gray-600 dark:text-gray-300">{{ $this->intro() }}</p>
    @if (! $this->isArranged())
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
            @if ($this->unplacedCount() === $this->totalCount())
                Everything is in automatic order. Choose <strong>Start arranging</strong> to keep today's order and start dragging, or use the row arrows to send one item to the top, the bottom or a position (that also starts arranging).
            @else
                {{ $this->unplacedCount() }} newer {{ $this->unplacedCount() === 1 ? 'item has' : 'items have' }} no position yet and appear after the placed ones. Choose <strong>Start arranging</strong> to place {{ $this->unplacedCount() === 1 ? 'it' : 'them' }} at the end, then drag.
            @endif
        </div>
    @endif
    {{ $this->table }}
</x-filament-panels::page>
