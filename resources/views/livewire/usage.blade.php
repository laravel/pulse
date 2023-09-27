<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        :name="match ($this->type) {
            'request_counts' => 'Top 10 Users Making Requests',
            'slow_endpoint_counts' => 'Top 10 Users Experiencing Slow Endpoints',
            'dispatched_job_counts' => 'Top 10 Users Dispatching Jobs',
            default => 'Application Usage'
        }"
        title="Time: {{ $time }}ms; Run At: {{ $runAt }};"
        details="{{ $this->usage === 'slow_endpoint_counts' ? (config('pulse.slow_endpoint_threshold').'ms threshold, ') : '' }}past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-dynamic-component :component="'pulse::icons.' . match ($this->type) {
                'request_counts' => 'arrow-trending-up',
                'slow_endpoint_counts' => 'clock',
                'dispatched_job_counts' => 'scale',
                default => 'cursor-arrow-rays'
            }" />
        </x-slot:icon>
        <x-slot:actions>
            @if (! $this->type)
                <div class="flex border border-gray-200 dark:border-gray-700 overflow-hidden rounded-md focus-within:ring">
                    <label class="px-3 flex items-center border-r border-gray-200 dark:border-gray-700 text-xs sm:text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap bg-gray-100 dark:bg-gray-800/50">Top 10 users</label>
                    <select
                        x-ref="select"
                        wire:model="usage"
                        wire:change="$dispatch('usage-changed', { usage: $event.target.value })"
                        class="overflow-ellipsis w-full border-0 pl-3 pr-8 py-1 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs sm:text-sm shadow-none focus:ring-0"
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
                </div>
            @endif
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::card-body :expand="$expand" wire:poll.5s="">
        <div
            x-data="{
                loadingNewDataset: false,
                init() {
                    Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                    @if (! $this->type)
                        Livewire.on('usage-changed', () => (this.loadingNewDataset = true))
                    @endif

                    Livewire.hook('commit', ({ component, succeed }) => {
                        if (component.name === $wire.__instance.name && component.snapshot.data.type === @js($this->type)) {
                            succeed(() => this.loadingNewDataset = false)
                        }
                    })
                }
            }"
            :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''"
        >
            @if (count($userRequestCounts) === 0)
                <x-pulse::no-results />
            @else
                <div class="grid grid-cols-1 @lg:grid-cols-2 @3xl:grid-cols-3 @6xl:grid-cols-4 gap-2">
                    @foreach ($userRequestCounts as $userRequestCount)
                        <div wire:key="{{ $userRequestCount['user']['name'] }}" class="flex items-center justify-between p-3 gap-3 bg-gray-50 dark:bg-gray-800/50 rounded">
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
    </x-pulse::card-body>
</x-pulse::card>
