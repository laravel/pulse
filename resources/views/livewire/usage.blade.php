<x-pulse::card class="col-span-{{ $cols }}">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500 dark:stroke-gray-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672L13.684 16.6m0 0l-2.51 2.225.569-9.47 5.227 7.917-3.286-.672zm-7.518-.267A8.25 8.25 0 1120.25 10.5M8.288 14.212A5.25 5.25 0 1117.25 10.5" />
            </svg>
            <span>
                <span title="Time: {{ $time }}ms; Run At: {{ $runAt }};">Application Usage</span>
                <small class="ml-2 text-gray-400 dark:text-gray-600 text-xs font-medium">past {{ $this->periodForHumans() }}@if ($this->usage === 'slow_endpoint_counts'), &gt;&equals;{{ config('pulse.slow_endpoint_threshold') }}ms @endif</small>
            </span>
        </x-pulse::card-title>
        <div class="flex items-center gap-2">
            <div class="text-sm text-gray-700 dark:text-gray-300">Top 10 users @if ($this->type) <span class="font-semibold">{{ match ($this->type) {
                'request_counts' => 'making requests',
                    'slow_endpoint_counts' => 'experiencing slow endpoints',
                    'dispatched_job_counts' => 'dispatching jobs',
                } }}</span> @endif
            </div>
            @if (! $this->type)
                <select
                    wire:model="usage"
                    wire:change="$dispatch('usage-changed', { usage: $event.target.value })"
                    class="rounded-md border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 py-1 text-sm dark:bg-gray-800"
                >
                    <option value="request_counts">
                        making requests
                    </option>
                    <option value="slow_endpoint_counts">
                        experiencing slow endpoints
                    </option>
                    <option value="dispatched_job_counts">
                        dispatching jobs
                    </option>
                </select>
            @endif
        </div>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <div
            x-data="{
                loadingNewDataset: false,
                init() {
                    Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                    @if (! $this->type)
                        Livewire.on('usage-changed', () => (this.loadingNewDataset = true))
                    @endif

                    Livewire.hook('commit', ({ component, succeed }) => {
                        if (component.name === 'usage' && component.snapshot.data.type === @js($this->type)) {
                            succeed(() => this.loadingNewDataset = false)
                        }
                    })
                }
            }"
        >
            <div>
                <div :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    @if (count($userRequestCounts) === 0)
                        <x-pulse::no-results />
                    @else
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($userRequestCounts as $userRequestCount)
                                <div wire:key="{{ $userRequestCount['user']['name'] }}" class="flex items-center justify-between p-3 gap-3 bg-gray-50 dark:bg-gray-800 rounded">
                                    <div class="flex items-center gap-3">
                                        @if ($userRequestCount['user']['avatar'] ?? false)
                                            <img height="32" width="32" src="{{ $userRequestCount['user']['avatar'] }}" loading="lazy" class="rounded-full">
                                        @endif
                                        <div class="overflow-hidden">
                                            <div class="text-sm text-gray-900 dark:text-gray-100 font-medium truncate">
                                                {{ $userRequestCount['user']['name'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                {{ $userRequestCount['user']['extra'] }}
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <b class="text-xl text-gray-900 dark:text-gray-100 font-bold tabular-nums">
                                            {{ number_format($userRequestCount['count']) }}
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
