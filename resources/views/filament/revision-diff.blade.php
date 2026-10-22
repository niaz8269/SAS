<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead><tr class="border-b border-gray-200 dark:border-white/10"><th class="py-2 pr-3">Field</th><th class="py-2 pr-3">Live now</th><th class="py-2">Proposed</th></tr></thead>
        <tbody>
        @forelse ($rows as $row)
            <tr class="border-b border-gray-100 align-top dark:border-white/5">
                <td class="py-2 pr-3 font-medium">{{ $row['label'] }}</td>
                <td class="py-2 pr-3 text-gray-600 dark:text-gray-400">{{ $row['current'] }}</td>
                <td class="py-2 font-medium">{{ $row['proposed'] }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="py-3 text-gray-500">No differences.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
