<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="Queues">
        <x-slot:icon>
            <x-pulse::icons.queue-list />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::card-body :expand="$expand" wire:poll.5s="">
        <x-pulse::table>
            <colgroup>
                <col width="100%" />
                <col width="0%" />
                <col width="0%" />
            </colgroup>
            <x-pulse::thead>
                <tr>
                    <x-pulse::th class="text-left">Name</x-pulse::th>
                    <x-pulse::th class="text-right">Pending</x-pulse::th>
                    <x-pulse::th class="text-right">Failed</x-pulse::th>
                </tr>
            </x-pulse::thead>
            <tbody>
                @foreach ($queues as $queue)
                    <tr class="h-2 first:h-0"></tr>
                    <tr wire:key="{{ $queue['queue'].$queue['connection'] }}">
                        <x-pulse::td class="text-gray-700 dark:text-gray-300 text-sm">
                            {{ $queue['queue'] }}{{ $showConnection ? '('.$queue['connection'].')' : '' }}
                        </x-pulse::td>
                        <x-pulse::td class="text-gray-700 dark:text-gray-300 text-sm font-bold text-right tabular-nums">
                            {{ number_format($queue['size']) }}
                        </x-pulse::td>
                        <x-pulse::td class="text-gray-700 dark:text-gray-300 text-sm font-bold text-right tabular-nums">
                            {{ number_format($queue['failed']) }}
                        </x-pulse::td>
                    </tr>
                @endforeach
            </tbody>
        </x-pulse::table>
    </x-pulse::card-body>
</x-pulse::card>
