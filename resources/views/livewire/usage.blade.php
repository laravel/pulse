<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        :name="match ($this->type) {
            'requests' => 'Top 10 Users Making Requests',
            'slow_requests' => 'Top 10 Users Experiencing Slow Endpoints',
            'jobs' => 'Top 10 Users Dispatching Jobs',
            default => 'Application Usage'
        }"
        title="Time: {{ number_format($time) }}ms; Run at: {{ $runAt }};"
        details="{{ $this->usage === 'slow_requests' ? ($slowRequestsConfig['threshold'].'ms threshold, ') : '' }}past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-dynamic-component :component="'pulse::icons.' . match ($this->type) {
                'requests' => 'arrow-trending-up',
                'slow_requests' => 'clock',
                'jobs' => 'scale',
                default => 'cursor-arrow-rays'
            }" />
        </x-slot:icon>
        <x-slot:actions>
            @if (! $this->type)
                <x-pulse::select
                    wire:model.live="usage"
                    label="Top 10 users"
                    :options="[
                        'requests' => 'making requests',
                        'slow_requests' => 'experiencing slow endpoints',
                        'jobs' => 'dispatching jobs',
                    ]"
                    class="flex-1"
                    @change="loading = true"
                />
            @endif
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::card-body :expand="$expand" wire:poll.5s="">
        @if (count($userRequestCounts) === 0)
            <x-pulse::no-results />
        @else
            <div class="grid grid-cols-1 @lg:grid-cols-2 @3xl:grid-cols-3 @6xl:grid-cols-4 gap-2">
                @foreach ($userRequestCounts as $userRequestCount)
                    <div wire:key="{{ $userRequestCount->user->id }}" class="flex items-center justify-between p-3 gap-3 bg-gray-50 dark:bg-gray-800/50 rounded">
                        <div class="flex items-center gap-3 overflow-hidden">
                            @if ($userRequestCount->user->avatar ?? false)
                                <img height="32" width="32" src="{{ $userRequestCount->user->avatar }}" loading="lazy" class="rounded-full">
                            @endif
                            <div class="overflow-hidden">
                                <div class="text-sm text-gray-900 dark:text-gray-100 font-medium truncate" title="{{ $userRequestCount->user->name }}">
                                    {{ $userRequestCount->user->name }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate" title="{{ $userRequestCount->user->extra }}">
                                    {{ $userRequestCount->user->extra }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <b class="text-xl text-gray-900 dark:text-gray-100 font-bold tabular-nums">
                                @php
                                    $sampleRate = match($this->usage) {
                                        'requests' => $userRequestsConfig['sample_rate'],
                                        'slow_requests' => $slowRequestsConfig['sample_rate'],
                                        'jobs' => $jobsConfig['sample_rate'],
                                    };
                                @endphp
                                @if ($sampleRate < 1)
                                    <span title="Sample rate: {{ $sampleRate }}, Raw value: {{ number_format($userRequestCount->count) }}">~{{ number_format($userRequestCount->count * (1 / $sampleRate)) }}</span>
                                @else
                                    {{ number_format($userRequestCount->count) }}
                                @endif
                            </b>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-pulse::card-body>
</x-pulse::card>
