@php
$supported = method_exists(Illuminate\Http\Client\Factory::class, 'globalMiddleware');
@endphp
<x-pulse::card class="col-span-3">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
            </svg>
            <span>
                <span title="Time: {{ $time }}ms; Run at: {{ $runAt }}">Slow Outgoing Requests</span>
                <small class="ml-2 text-gray-400 text-xs font-medium">past {{ $this->periodForHumans() }}, &gt;&equals;{{ config('pulse.slow_outgoing_request_threshold') }}ms</small>
            </span>
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <script>
            const initialSlowOutgoingRequestsDataLoaded = @js($initialDataLoaded)
        </script>
        <div x-data="{
            initialDataLoaded: initialSlowOutgoingRequestsDataLoaded,
            loadingNewDataset: false,
            init() {
                Livewire.on('periodChanged', () => (this.loadingNewDataset = true))

                window.addEventListener('slow-outgoing-requests:dataLoaded', () => {
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
                    @if (! $supported)
                        <div class="flex flex-col items-center justify-center p-4 py-6">
                            <div class="bg-gray-50 rounded-full text-xs leading-none px-2 py-1 text-gray-500">
                                Requires laravel/framework v10.14+
                            </div>
                        </div>
                    @else
                        @if ($initialDataLoaded && count($slowOutgoingRequests) === 0)
                            <x-pulse::no-results />
                        @elseif ($initialDataLoaded && count($slowOutgoingRequests) > 0)
                            <x-pulse::table class="table-fixed">
                                <x-pulse::thead>
                                    <tr>
                                        <x-pulse::th class="text-left">URI</x-pulse::th>
                                        <x-pulse::th class="text-right w-24">Count</x-pulse::th>
                                        <x-pulse::th class="text-right w-24">Slowest</x-pulse::th>
                                    </tr>
                                </x-pulse::thead>
                                <tbody>
                                    @foreach ($slowOutgoingRequests as $request)
                                        <tr>
                                            <x-pulse::td>
                                                <code class="block text-xs text-gray-900 truncate" title="{{ $request->uri }}">
                                                    {{ $request->uri }}
                                                </code>
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
