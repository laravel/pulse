<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Slow Outgoing Requests"
        title="Time: {{ $time }}; Run at: {{ $runAt }};"
        details="{{ config('pulse.slow_outgoing_request_threshold') }}ms threshold, past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.cloud-arrow-up />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::card-body wire:poll.5s="">
        <div x-data="{
            loadingNewDataset: false,
            init() {
                Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                Livewire.hook('commit', ({ component, succeed }) => {
                    if (component.name === 'slow-outgoing-requests') {
                        succeed(() => this.loadingNewDataset = false)
                    }
                })
            }
        }">
            <div>
                <div :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    @if (! $supported)
                        <div class="flex flex-col items-center justify-center p-4 py-6">
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-full text-xs leading-none px-2 py-1 text-gray-500 dark:text-gray-400">
                                Requires laravel/framework v10.14+
                            </div>
                        </div>
                    @else
                        @if (count($slowOutgoingRequests) === 0)
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
                                        <x-pulse::th class="text-left">URI</x-pulse::th>
                                        <x-pulse::th class="text-right">Count</x-pulse::th>
                                        <x-pulse::th class="text-right">Slowest</x-pulse::th>
                                    </tr>
                                </x-pulse::thead>
                                <tbody>
                                    @foreach ($slowOutgoingRequests as $request)
                                        @php
                                            [$method, $uri] = explode(' ', $request->uri, 2);
                                        @endphp
                                        <tr>
                                            <x-pulse::td>
                                                <x-pulse::http-method-badge :method="$method" />
                                            </x-pulse::td>
                                            <x-pulse::td class="max-w-[1px]">
                                                <div class="flex items-center" title="{{ $uri }}">
                                                    <img wire:ignore src="https://unavatar.io/{{ parse_url($uri, PHP_URL_HOST) }}?fallback=false" class="w-4 h-4 mr-2" onerror="this.style.display='none'" />
                                                    <code class="block text-xs text-gray-900 dark:text-gray-100 truncate">
                                                        {{ $uri }}
                                                    </code>
                                                </div>
                                            </x-pulse::td>
                                            <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm w-24 tabular-nums">
                                                <strong>{{ number_format($request->count) }}</strong>
                                            </x-pulse::td>
                                            <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm w-24 whitespace-nowrap tabular-nums">
                                                @if ($request->slowest === null)
                                                    <strong>Unknown</strong>
                                                @else
                                                    <strong>{{ number_format($request->slowest) ?: '<1' }}</strong> ms
                                                @endif
                                            </x-pulse::td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-pulse::table>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </x-pulse::card-body>
</x-pulse::card>
