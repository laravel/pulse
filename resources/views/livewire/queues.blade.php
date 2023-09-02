<x-pulse::card class="col-span-{{ $cols }}">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500 dark:stroke-gray-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
            </svg>
            Queues
        </x-pulse::card-title>
    </x-slot:title>

    <x-pulse::table wire:poll.5s="">
        <x-pulse::thead>
            <tr>
                <x-pulse::th class="w-full text-left">Name</x-pulse::th>
                <x-pulse::th class="text-right">Pending</x-pulse::th>
                <x-pulse::th class="text-right">Failed</x-pulse::th>
            </tr>
        </x-pulse::thead>
        <tbody>
            @foreach ($queues as $queue)
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
</x-pulse::card>
