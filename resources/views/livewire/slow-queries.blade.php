<x-pulse::card class="col-span-3">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>
                <span title="Time: {{ $time }}ms; Run at: {{ $runAt }}">Slow Queries</span>
                <small class="ml-2 text-gray-400 text-xs font-medium">past {{ match ($this->period) {
                    '6_hours' => '6 hours',
                    '24_hours' => '24 hours',
                    '7_days' => '7 days',
                    default => 'hour',
                } }}, &gt;&equals;{{ config('pulse.slow_query_threshold') }}ms</small>
            </span>
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <script>
            const initialSlowQueriesDataLoaded = @js($initialDataLoaded)
        </script>
        <div x-data="{
            initialDataLoaded: initialSlowQueriesDataLoaded,
            loadingNewDataset: false,
            init() {
                Livewire.on('periodChanged', () => (this.loadingNewDataset = true))

                window.addEventListener('slow-queries:dataLoaded', () => {
                    this.initialDataLoaded = true
                    this.loadingNewDataset = false
                })

                if (! this.initialDataLoaded) {
                    @this.loadData()
                }
            }
        }">
            <x-pulse::loading-indicator x-cloak x-show="! initialDataLoaded"/>
            <div x-cloak x-show="initialDataLoaded">
                <div :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    @if ($initialDataLoaded && count($slowQueries) === 0)
                        <x-pulse::no-results />
                    @elseif ($initialDataLoaded && count($slowQueries) > 0)
                        <x-pulse::table class="table-fixed">
                            <x-pulse::thead>
                                <tr>
                                    <x-pulse::th class="text-left">Query</x-pulse::th>
                                    <x-pulse::th class="text-right w-24">Count</x-pulse::th>
                                    <x-pulse::th class="text-right w-24">Slowest</x-pulse::th>
                                </tr>
                            </x-pulse::thead>
                            <tbody>
                                @foreach ($slowQueries as $query)
                                    <tr>
                                        <x-pulse::td>
                                            <code class="block text-xs text-gray-900 truncate" title="{{ $query->sql }}">
                                                {{ $query->sql }}
                                            </code>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm w-24">
                                            <strong>{{ $query->count }}</strong>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm w-24 whitespace-nowrap">
                                            @if ($query->slowest === null)
                                                <strong>Unknown</strong>
                                            @else
                                                <strong>{{ $query->slowest ?: '<1' }}</strong> ms
                                            @endif
                                        </x-pulse::td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-pulse::table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-pulse::card>
