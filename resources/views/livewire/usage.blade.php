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

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($userRequestCounts->isEmpty())
            <x-pulse::no-results />
        @else
            @unless ($successfullyResolved)
                <div class="mb-2 col-span-full flex justify-between items-center text-purple-700 dark:text-purple-200 bg-purple-50 dark:bg-purple-800/20 p-3 rounded">
                    <p class="text-xs font-medium flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="w-4 h-4 fill-purple-500 dark:fill-purple-400">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        Unable to resolve users
                    </p>
                    <a class="text-xs font-medium hover:text-purple-800 dark:hover:text-purple-100" href="https://laravel.com/docs/pulse#application-usage-card">Documentation<span aria-hidden="true"> →</span></a>
                </div>
            @endunless

            <div class="grid grid-cols-1 @lg:grid-cols-2 @3xl:grid-cols-3 @6xl:grid-cols-4 gap-2">
                @foreach ($userRequestCounts as $userRequestCount)
                    <x-pulse::user-card wire:key="{{ $userRequestCount->user->id }}" :name="$userRequestCount->user->name" :extra="$userRequestCount->user->extra">
                        @if ($userRequestCount->user->avatar ?? false)
                            <x-slot:avatar>
                                <img height="32" width="32" src="{{ $userRequestCount->user->avatar }}" loading="lazy" class="rounded-full">
                            </x-slot:avatar>
                        @endif

                        <x-slot:stats>
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
                        </x-slot:stats>
                    </x-pulse::user-card>
                @endforeach
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
