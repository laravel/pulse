<x-pulse::card class="col-span-{{ $cols }}">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
            </svg>
            Queues
        </x-pulse::card-title>
    </x-slot:title>

    <table wire:poll.5s class="w-full border-separate border-spacing-y-2">
        <thead class="sticky top-0 p-2 bg-white">
            <tr class="p-2">
                <th class="text-xs text-gray-500 uppercase px-3 text-left">
                    Name
                </th>
                <th class="text-xs text-gray-500 uppercase px-3 text-right">
                    Pending
                </th>
                <th class="text-xs text-gray-500 uppercase px-3 text-right">
                    Failed
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach ($queues as $queue)
                <tr wire:key="{{ $queue['queue'].$queue['connection'] }}">
                    <td class="rounded-l-md bg-gray-50 px-3 py-2 text-left">
                        <div class="text-gray-700 text-sm">
                            {{ $queue['queue'] }}{{ $showConnection ? '('.$queue['connection'].')' : '' }}
                        </div>
                    </td>
                    <td class="bg-gray-50 px-3 py-2 text-right">
                        <div class="text-gray-700 text-sm font-bold">
                            {{ number_format($queue['size']) }}
                        </div>
                    </td>
                    <td class="rounded-r-md bg-gray-50 px-3 py-2 text-right">
                        <div class="text-gray-700 text-sm font-bold">
                            {{ number_format($queue['failed']) }}
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-pulse::card>
