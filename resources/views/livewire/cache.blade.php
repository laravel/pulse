<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Cache"
        title="Global Time: {{ $allTime }}; Global run at: {{ $allRunAt }}; Monitored Time: {{ $monitoredTime }}; Monitored run at: {{ $monitoredRunAt }};"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.rocket-launch />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::card-body wire:poll.5s="">
        <div x-data="{
            loadingNewDataset: false,
            init() {
                Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                Livewire.hook('commit', ({ component, succeed }) => {
                    if (component.name === 'cache') {
                        succeed(() => this.loadingNewDataset = false)
                    }
                })
            }
        }">
            <div :class="[loadingNewDataset ? 'opacity-25 animate-pulse' : '', 'space-y-6']">
                <div class="grid grid-cols-3 text-center">
                    <div>
                        <span class="text-xl uppercase font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                            {{ number_format($allCacheInteractions->hits) }}
                        </span>
                        <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-400">
                            Hits
                        </span>
                    </div>
                    <div>
                        <span class="text-xl uppercase font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                            {{ number_format($allCacheInteractions->count - $allCacheInteractions->hits) }}
                        </span>
                        <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-400">
                            Misses
                        </span>
                    </div>
                    <div>
                        <span class="text-xl uppercase font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                            {{ $allCacheInteractions->count > 0 ? round(($allCacheInteractions->hits / $allCacheInteractions->count) * 100, 2).'%' : '-' }}
                        </span>
                        <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-400">
                            Hit Rate
                        </span>
                    </div>
                </div>
                @if ($monitoredCacheInteractions->isEmpty())
                    <div class="flex flex-col items-center justify-center p-4 py-6">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-full text-xs leading-none px-2 py-1 text-gray-500 dark:text-gray-400">
                            No keys configured to monitor
                        </div>
                    </div>
                @else
                    <x-pulse::table>
                        <colgroup>
                            <col width="100%" />
                            <col width="0%" />
                            <col width="0%" />
                            <col width="0%" />
                        </colgroup>
                        <x-pulse::thead>
                            <tr>
                                <x-pulse::th class="text-left">Name</x-pulse::th>
                                <x-pulse::th class="text-right">Hits</x-pulse::th>
                                <x-pulse::th class="text-right">Misses</x-pulse::th>
                                <x-pulse::th class="text-right whitespace-nowrap">Hit Rate</x-pulse::th>
                            </tr>
                        </x-pulse::thead>
                        <tbody>
                            @foreach ($monitoredCacheInteractions as $interaction)
                                <tr class="h-2 first:h-0"></tr>
                                <tr wire:key="{{ $interaction->key }}">
                                    <x-pulse::td>
                                        <code class="block text-xs text-gray-900 dark:text-gray-100">
                                            {{ $interaction->key }}
                                        </code>
                                    </x-pulse::td>
                                    <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm tabular-nums">
                                        <strong>{{ number_format($interaction->hits) }}</strong>
                                    </x-pulse::td>
                                    <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm whitespace-nowrap tabular-nums">
                                        <strong>{{ number_format($interaction->count - $interaction->hits) }}</strong>
                                    </x-pulse::td>
                                    <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm whitespace-nowrap tabular-nums">
                                        <strong>{{ $interaction->count > 0 ? round(($interaction->hits / $interaction->count) * 100, 2).'%' : '-' }}</strong>
                                    </x-pulse::td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-pulse::table>
                @endif
            </div>
        </div>
    </x-pulse::card-body>
</x-pulse::card>
