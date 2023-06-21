<x-pulse::card class="col-span-3">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            <span>
                <span title="Time: {{ $time }}ms; Run At: {{ $runAt }};">Application Usage</span>
                <small class="ml-2 text-gray-400 text-xs font-medium">past {{ match ($this->period) {
                    '6_hours' => '6 hours',
                    '24_hours' => '24 hours',
                    '7_days' => '7 days',
                    default => 'hour',
                } }}@if ($this->usage === 'slow_endpoint_counts'), &gt;&equals;{{ config('pulse.slow_endpoint_threshold') }}ms @endif</small>
            </span>
        </x-pulse::card-title>
        <div class="flex items-center gap-2">
            <div class="text-sm text-gray-700">Top 10 users</div>
            <select
                wire:model="usage"
                wire:change="$emit('usageChanged', $event.target.value)"
                class="rounded-md border-gray-200 text-gray-700 py-1 text-sm"
            >
                <option value="request_counts">
                    making requests
                </option>
                <option value="slow_endpoint_counts">
                    experiencing slow endpoints
                </option>
                <option value="dispatched_job_count">
                    dispatching jobs
                </option>
            </select>
        </div>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <script>
            const initialUsageDataLoaded = @js($initialDataLoaded)
        </script>
        <div x-data="{
            initialDataLoaded: initialUsageDataLoaded,
            loadingNewDataset: false,
            init() {
                ['periodChanged', 'usageChanged'].forEach(event => Livewire.on(event, () => (this.loadingNewDataset = true)))

                window.addEventListener('usage:dataLoaded', () => {
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
                    @if ($initialDataLoaded && count($userRequestCounts) === 0)
                        <x-pulse::no-results />
                    @elseif ($initialDataLoaded && count($userRequestCounts) > 0)
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($userRequestCounts ?? [] as $userRequestCount)
                                <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded">
                                    <div>
                                        <div class="text-sm text-gray-900 font-medium">
                                            {{ $userRequestCount['user']['name'] }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $userRequestCount['user']['email'] }}
                                        </div>
                                    </div>
                                    <div>
                                        <b class="text-xl text-gray-900 font-bold">
                                            {{ $userRequestCount['count'] }}
                                        </b>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-pulse::card>
