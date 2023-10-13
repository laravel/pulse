<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Slow Outgoing Requests"
        title="Time: {{ number_format($time) }}ms; Run at: {{ $runAt }};"
        details="{{ $threshold }}ms threshold, past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.cloud-arrow-up />
        </x-slot:icon>
        <x-slot:actions>
            @php $message = 'URI groups: '.count($groups); @endphp
            <button title="{{ $message }}" @click="alert('{{ $message }}')">
                <x-pulse::icons.information-circle class="w-5 h-5 stroke-gray-400 dark:stroke-gray-600" />
            </button>
        </x-slot:actions>
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
            @if (! $supported)
                <div class="flex flex-col items-center justify-center p-4 py-6">
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-full text-xs leading-none px-2 py-1 text-gray-500 dark:text-gray-400">
                        Requires laravel/framework v10.14+
                    </div>
                </div>
            @else
                @if ($slowOutgoingRequests->isEmpty())
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
                                <x-pulse::th class="text-left">URI</x-pulse::th>
                                <x-pulse::th class="text-right">Count</x-pulse::th>
                                <x-pulse::th class="text-right">Slowest</x-pulse::th>
                            </tr>
                        </x-pulse::thead>
                        <tbody>
                            @foreach ($slowOutgoingRequests->take(100) as $request)
                                @php
                                    [$method, $uri] = explode(' ', $request->uri, 2);
                                @endphp
                                <tr class="h-2 first:h-0"></tr>
                                <tr wire:key="{{ $uri }}">
                                    <x-pulse::td>
                                        <x-pulse::http-method-badge :method="$method" />
                                    </x-pulse::td>
                                    <x-pulse::td class="max-w-[1px]">
                                        <div class="flex items-center" title="{{ $uri }}">
                                            @if ($host = parse_url($uri, PHP_URL_HOST))
                                                <img wire:ignore src="https://unavatar.io/{{ $host }}?fallback=false" loading="lazy" class="w-4 h-4 mr-2" onerror="this.style.display='none'" />
                                            @endif
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
    </x-pulse::card-body>
</x-pulse::card>
