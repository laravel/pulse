<x-pulse::card class="col-span-{{ $cols }}">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
            </svg>
            <span>
                <span title="Time: {{ $time }}ms; Run at: {{ $runAt }}">Slow Outgoing Requests</span>
                <small class="ml-2 text-gray-400 text-xs font-medium">{{ config('pulse.slow_outgoing_request_threshold') }}ms threshold, past {{ $this->periodForHumans() }}</small>
            </span>
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
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
                            <div class="bg-gray-50 rounded-full text-xs leading-none px-2 py-1 text-gray-500">
                                Requires laravel/framework v10.14+
                            </div>
                        </div>
                    @else
                        @if (count($slowOutgoingRequests) === 0)
                            <x-pulse::no-results />
                        @else
                            <x-pulse::table class="table-fixed">
                                <x-pulse::thead>
                                    <tr>
                                        <x-pulse::th class="text-left w-[70px]">Method</x-pulse::th>
                                        <x-pulse::th class="text-left">URI</x-pulse::th>
                                        <x-pulse::th class="text-right w-24">Count</x-pulse::th>
                                        <x-pulse::th class="text-right w-24">Slowest</x-pulse::th>
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
                                            <x-pulse::td>
                                                <div class="flex items-center">
                                                    <img wire:ignore src="https://unavatar.io/{{ parse_url($uri, PHP_URL_HOST) }}?fallback=false" class="w-4 h-4 mr-2" onerror="this.style.display='none'" />
                                                    <code class="block text-xs text-gray-900 truncate" title="{{ $uri }}">
                                                        {{ $uri }}
                                                    </code>
                                                </div>
                                            </x-pulse::td>
                                            <x-pulse::td class="text-right text-gray-700 text-sm w-24">
                                                <strong>{{ number_format($request->count) }}</strong>
                                            </x-pulse::td>
                                            <x-pulse::td class="text-right text-gray-700 text-sm w-24 whitespace-nowrap">
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
    </div>
</x-pulse::card>
