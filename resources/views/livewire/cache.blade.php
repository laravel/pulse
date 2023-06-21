<x-pulse::card
    class="col-span-2"
    wire:poll=""
>
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
            <div x-cloak x-show="initialDataLoaded" :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                @if ($initialDataLoaded)
                    <div class="grid grid-cols-3">
                        <div>
                            <div class="text-xs uppercase font-bold text-gray-500">
                                Hits
                            </div>
                            <div class="text-xl uppercase font-bold text-gray-700">
                                {{ $cacheInteractions->hits }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs uppercase font-bold text-gray-500">
                                Misses
                            </div>
                            <div class="text-xl uppercase font-bold text-gray-700">
                                {{ $cacheInteractions->count - $cacheInteractions->hits }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs uppercase font-bold text-gray-500">
                                Hit Rate
                            </div>
                            <div class="text-xl uppercase font-bold text-gray-700">
                                {{ $cacheInteractions->count > 0 ? round($cacheInteractions->hits / $cacheInteractions->count, 2).'%' : '-' }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-pulse::card>
