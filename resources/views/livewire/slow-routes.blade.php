<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Slow Routes"
        title="Time: {{ $time }}; Run at: {{ $runAt }};"
        details="{{ config('pulse.slow_endpoint_threshold') }}ms threshold, past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.arrows-left-right />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::card-body :scroll="$scroll" wire:poll.5s="">
        <div
            x-data="{
                loadingNewDataset: false,
                init() {
                    Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                    Livewire.hook('commit', ({ component, succeed }) => {
                        if (component.name === 'slow-routes') {
                            succeed(() => this.loadingNewDataset = false)
                        }
                    })
                }
            }"
            :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''"
        >
            @if (count($slowRoutes) === 0)
                <x-pulse::no-results />
            @else
                <x-pulse::table>
                    <colgroup>
                        <col width="0%" />
                        <col width="100%" />
                        <col width="0%" />
                        <col width="0%" />
                    </colgroup>
                    <x-pulse::thead>
                        <tr>
                            <x-pulse::th class="text-left">Method</x-pulse::th>
                            <x-pulse::th class="text-left">Route</x-pulse::th>
                            <x-pulse::th class="text-right">Count</x-pulse::th>
                            <x-pulse::th class="text-right">Slowest</x-pulse::th>
                        </tr>
                    </x-pulse::thead>
                    <tbody>
                        @foreach ($slowRoutes as $route)
                            @php
                                [$method, $uri] = explode(' ', $route['uri'], 2);
                            @endphp
                            <tr class="h-2 first:h-0"></tr>
                            <tr>
                                <x-pulse::td>
                                    <x-pulse::http-method-badge :method="$method" />
                                </x-pulse::td>
                                <x-pulse::td class="overflow-hidden max-w-[1px]">
                                    <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $uri }}">
                                        {{ $uri }}
                                    </code>
                                    @if ($route['action'])
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate" table="{{ $route['action'] }}">
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
    </x-pulse::card-body>
</x-pulse::card>
