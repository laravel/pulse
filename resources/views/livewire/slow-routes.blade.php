<x-pulse::card class="col-span-3">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
            <span>
                <span title="Time: {{ $time }}ms; Run at: {{ $runAt }}">Slow Routes</span>
                <small class="ml-2 text-gray-400 text-xs font-medium">past {{ match ($this->period) {
                    '6_hours' => '6 hours',
                    '24_hours' => '24 hours',
                    '7_days' => '7 days',
                    default => 'hour',
                } }}, &gt;&equals;{{ config('pulse.slow_endpoint_threshold') }}ms</small>
            </span>
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <script>
            const initialSlowRoutesDataLoaded = @js($initialDataLoaded)
        </script>
        <div x-data="{
            initialDataLoaded: initialSlowRoutesDataLoaded,
            loadingNewDataset: false,
            init() {
                Livewire.on('periodChanged', () => (this.loadingNewDataset = true))

                window.addEventListener('slow-routes:dataLoaded', () => {
                    this.initialDataLoaded = true
                    this.loadingNewDataset = false
                })

                if (! this.initialDataLoaded) {
                    @this.loadData()
                }
            }
        }">
            <x-pulse::loading-indicator x-cloak x-show="! initialDataLoaded" />
            <div x-cloak x-show="initialDataLoaded">
                <div :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    @if ($initialDataLoaded && count($slowRoutes) === 0)
                        <x-pulse::no-results />
                    @elseif ($initialDataLoaded && count($slowRoutes) > 0)
                        <x-pulse::table>
                            <x-pulse::thead>
                                <tr>
                                    <x-pulse::th class="w-full text-left">Route</x-pulse::th>
                                    <x-pulse::th class="text-right">Count</x-pulse::th>
                                    <x-pulse::th class="text-right">Slowest</x-pulse::th>
                                </tr>
                            </x-pulse::thead>
                            <tbody>
                                @foreach ($slowRoutes as $route)
                                    <tr>
                                        <x-pulse::td>
                                            <code class="block text-xs text-gray-900">
                                                {{ $route['uri'] }}
                                            </code>
                                            @if ($route['action'])
                                                <p class="mt-1 text-xs text-gray-500">
                                                    {{ $route['action'] }}
                                                </p>
                                            @endif
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm">
                                            <strong>{{ number_format($route['request_count']) }}</strong>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm whitespace-nowrap">
                                            @if ($route['slowest_duration'] === null)
                                                <strong>Unknown</strong>
                                            @else
                                                <strong>{{ number_format($route['slowest_duration']) ?: '<1' }}</strong> ms
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
