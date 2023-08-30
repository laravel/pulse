<x-pulse::card class="col-span-3">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
            <span>
                <span title="Time: {{ $time }}ms; Run at: {{ $runAt }}">Slow Queries</span>
                <small class="ml-2 text-gray-400 text-xs font-medium">past {{ $this->periodForHumans() }}, &gt;&equals;{{ config('pulse.slow_query_threshold') }}ms</small>
            </span>
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <div x-data="{
            loadingNewDataset: false,
            init() {
                Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                Livewire.hook('commit', ({ component, succeed }) => {
                    if (component.name === 'slow-queries') {
                        succeed(() => this.loadingNewDataset = false)
                    }
                })
            }
        }">
            <div>
                <div :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    @if (count($slowQueries) === 0)
                        <x-pulse::no-results />
                    @else
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
                                        <x-pulse::td class="!p-0">
                                            <code class="bg-gray-800 rounded-md h-full p-3 text-gray-200 block text-xs truncate" title="{{ $query->sql }}">
                                                {{ $query->sql }}
                                            </code>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm w-24">
                                            <strong>{{ number_format($query->count) }}</strong>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm w-24 whitespace-nowrap">
                                            @if ($query->slowest === null)
                                                <strong>Unknown</strong>
                                            @else
                                                <strong>{{ number_format($query->slowest) ?: '<1' }}</strong> ms
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
