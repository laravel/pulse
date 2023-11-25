<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Slow Requests"
        title="Time: {{ number_format($time) }}ms; Run at: {{ $runAt }};"
        details="{{ $config['threshold'] }}ms threshold, past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.arrows-left-right />
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
            class="min-h-full flex flex-col"
            :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''"
        >
            @if ($slowRequests->isEmpty())
                <x-pulse::no-results class="flex-1" />
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
                        @foreach ($slowRequests->take(100) as $slowRequest)
                            <tr class="h-2 first:h-0"></tr>
                            <tr>
                                <x-pulse::td>
                                    <x-pulse::http-method-badge :method="$slowRequest->method" />
                                </x-pulse::td>
                                <x-pulse::td class="overflow-hidden max-w-[1px]">
                                    <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $slowRequest->uri }}">
                                        {{ $slowRequest->uri }}
                                    </code>
                                    @if ($slowRequest->action)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate" table="{{ $slowRequest->action }}">
                                            {{ $slowRequest->action }}
                                        </p>
                                    @endif
                                </x-pulse::td>
                                <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 font-bold text-sm tabular-nums">
                                    @if ($config['sample_rate'] < 1)
                                        <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($slowRequest->count) }}">~{{ number_format($slowRequest->count * (1 / $config['sample_rate'])) }}</span>
                                    @else
                                        {{ number_format($slowRequest->count) }}
                                    @endif
                                </x-pulse::td>
                                <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm whitespace-nowrap tabular-nums">
                                    @if ($slowRequest->slowest === null)
                                        <strong>Unknown</strong>
                                    @else
                                        <strong>{{ number_format($slowRequest->slowest) ?: '<1' }}</strong> ms
                                    @endif
                                </x-pulse::td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-pulse::table>

                @if ($slowRequests->count() > 100)
                    <div class="mt-2 text-xs text-gray-400 text-center">Limited to 100 entries</div>
                @endif
            @endif
        </div>
    </x-pulse::card-body>
</x-pulse::card>
