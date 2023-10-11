<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Cache"
        title="Global Time: {{ $allTime }}; Global run at: {{ $allRunAt }}; Key Time: {{ $keyTime }}; Key run at: {{ $keyRunAt }};"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.rocket-launch />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::card-body :expand="$expand" wire:poll.5s="">
        <div
            x-data="{
                loadingNewDataset: false,
                init() {
                    Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                    Livewire.hook('commit', ({ component, succeed }) => {
                        if (component.name === $wire.__instance.name) {
                            succeed(() => this.loadingNewDataset = false)
                        }
                    })
                }
            }"
            :class="[loadingNewDataset ? 'opacity-25 animate-pulse' : '', 'space-y-6']"
        >
            @if ($allCacheInteractions->count === 0)
                <x-pulse::no-results />
            @else
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="flex flex-col justify-center @sm:block">
                        <span class="text-xl uppercase font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                            {{ number_format($allCacheInteractions->hits) }}
                        </span>
                        <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-400">
                            Hits
                        </span>
                    </div>
                    <div class="flex flex-col justify-center @sm:block">
                        <span class="text-xl uppercase font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                            {{ number_format($allCacheInteractions->count - $allCacheInteractions->hits) }}
                        </span>
                        <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-400">
                            Misses
                        </span>
                    </div>
                    <div class="flex flex-col justify-center @sm:block">
                        <span class="text-xl uppercase font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                            {{ $allCacheInteractions->count > 0 ? round(($allCacheInteractions->hits / $allCacheInteractions->count) * 100, 2).'%' : '-' }}
                        </span>
                        <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-400">
                            Hit Rate
                        </span>
                    </div>
                </div>
                <x-pulse::table>
                    <colgroup>
                        <col width="100%" />
                        <col width="0%" />
                        <col width="0%" />
                        <col width="0%" />
                    </colgroup>
                    <x-pulse::thead>
                        <tr>
                            <x-pulse::th class="text-left">Key</x-pulse::th>
                            <x-pulse::th class="text-right">Hits</x-pulse::th>
                            <x-pulse::th class="text-right">Misses</x-pulse::th>
                            <x-pulse::th class="text-right whitespace-nowrap">Hit Rate</x-pulse::th>
                        </tr>
                    </x-pulse::thead>
                    <tbody>
                        @foreach ($cacheKeyInteractions->take(100) as $interaction)
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
    </x-pulse::card-body>
</x-pulse::card>
