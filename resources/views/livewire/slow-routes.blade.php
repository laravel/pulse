<x-pulse::card class="col-span-{{ $cols }}">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500 dark:stroke-gray-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
            <span>
                <span title="Time: {{ $time }}ms; Run at: {{ $runAt }}">Slow Routes</span>
                <small class="ml-2 text-gray-400 dark:text-gray-600 text-xs font-medium">{{ config('pulse.slow_endpoint_threshold') }}ms threshold, past {{ $this->periodForHumans() }}</small>
            </span>
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <div x-data="{
            loadingNewDataset: false,
            init() {
                Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                Livewire.hook('commit', ({ component, succeed }) => {
                    if (component.name === 'slow-routes') {
                        succeed(() => this.loadingNewDataset = false)
                    }
                })
            }
        }">
            <div>
                <div :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    @if (count($slowRoutes) === 0)
                        <x-pulse::no-results />
                    @else
                        <x-pulse::table>
                            <x-pulse::thead>
                                <tr>
                                    <x-pulse::th class="text-left w-[70px]">Method</x-pulse::th>
                                    <x-pulse::th class="w-full text-left">Route</x-pulse::th>
                                    <x-pulse::th class="text-right">Count</x-pulse::th>
                                    <x-pulse::th class="text-right">Slowest</x-pulse::th>
                                </tr>
                            </x-pulse::thead>
                            <tbody>
                                @foreach ($slowRoutes as $route)
                                    @php
                                        [$method, $uri] = explode(' ', $route['uri'], 2);
                                    @endphp
                                    <tr>
                                        <x-pulse::td>
                                            <x-pulse::http-method-badge :method="$method" />
                                        </x-pulse::td>
                                        <x-pulse::td>
                                            <code class="text-xs text-gray-900 dark:text-gray-100">
                                                {{ $uri }}
                                            </code>
                                            @if ($route['action'])
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $route['action'] }}
                                                </p>
                                            @endif
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm tabular-nums">
                                            <strong>{{ number_format($route['request_count']) }}</strong>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm whitespace-nowrap tabular-nums">
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
