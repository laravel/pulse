<x-pulse::card class="col-span-3">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
            </svg>
            <span>
                Cache
                <small class="ml-2 text-gray-400 text-xs font-medium">Past 7 days</small>
            </span>
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <script>
            const initialCacheDataLoaded = @js($initialDataLoaded)
        </script>
        <div x-data="{
            initialDataLoaded: initialCacheDataLoaded,
            loadingNewDataset: false,
            init() {
                Livewire.on('periodChanged', () => (this.loadingNewDataset = true))

                window.addEventListener('cache:dataLoaded', () => {
                    this.initialDataLoaded = true
                    this.loadingNewDataset = false
                })

                if (! this.initialDataLoaded) {
                    @this.loadData()
                }
            }
        }">
            <x-pulse::loading-indicator x-cloak x-show="! initialDataLoaded" />
            <div x-cloak x-show="initialDataLoaded" :class="[loadingNewDataset ? 'opacity-25 animate-pulse' : '', 'space-y-6']">
                @if ($initialDataLoaded)
                    <div class="grid grid-cols-3 text-center">
                        <div>
                            <span class="text-xl uppercase font-bold text-gray-700">
                                {{ number_format($allCacheInteractions->hits) }}
                            </span>
                            <span class="text-xs uppercase font-bold text-gray-500">
                                Hits
                            </span>
                        </div>
                        <div>
                            <span class="text-xl uppercase font-bold text-gray-700">
                                {{ number_format($allCacheInteractions->count - $allCacheInteractions->hits) }}
                            </span>
                            <span class="text-xs uppercase font-bold text-gray-500">
                                Misses
                            </span>
                        </div>
                        <div>
                            <span class="text-xl uppercase font-bold text-gray-700">
                                {{ $allCacheInteractions->count > 0 ? round(($allCacheInteractions->hits / $allCacheInteractions->count) * 100, 2).'%' : '-' }}
                            </span>
                            <span class="text-xs uppercase font-bold text-gray-500">
                                Hit Rate
                            </span>
                        </div>
                    </div>
                    @if ($monitoredCacheInteractions === [])
                        <div class="flex flex-col items-center justify-center p-4 py-6">
                            <div class="bg-gray-50 rounded-full text-xs leading-none px-2 py-1 text-gray-500">
                                No keys configured to monitor
                            </div>
                        </div>

                        <div class="flex h-32 items-center text-center text-"></div>
                    @else
                        <x-pulse::table>
                            <x-pulse::thead>
                                <tr>
                                    <x-pulse::th class="w-full text-left">Name</x-pulse::th>
                                    <x-pulse::th class="text-right">Hits</x-pulse::th>
                                    <x-pulse::th class="text-right">Misses</x-pulse::th>
                                    <x-pulse::th class="text-right whitespace-nowrap">Hit Rate</x-pulse::th>
                                </tr>
                            </x-pulse::thead>
                            <tbody>
                                @foreach ($monitoredCacheInteractions as $interaction)
                                    <tr>
                                        <x-pulse::td>
                                            <code class="block text-xs text-gray-900">
                                                {{ $interaction->key }}
                                            </code>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm">
                                            <strong>{{ number_format($interaction->hits) }}</strong>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm whitespace-nowrap">
                                            <strong>{{ number_format($interaction->count - $interaction->hits) }}</strong>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm whitespace-nowrap">
                                            <strong>{{ $interaction->count > 0 ? round(($interaction->hits / $interaction->count) * 100, 2).'%' : '-' }}</strong>
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
</x-pulse::card>
