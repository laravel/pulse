<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        :name="match ($this->type) {
            'requests' => __('Top 10 Users Making Requests'),
            'slow_requests' => __('Top 10 Users Experiencing Slow Endpoints'),
            'jobs' => __('Top 10 Users Dispatching Jobs'),
            default => __('Application Usage')
        }"
        title="{{ __('Time: :timems', ['time' => number_format($time)]) }}; {{ __('Run at:') }} {{ $runAt }};"
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
                    label="{{ __('Top 10 users') }}"
                    :options="[
                        'requests' => '{{ __("making requests") }}',
                        'slow_requests' => '{{ __("experiencing slow endpoints") }}',
                        'jobs' => '{{ __("dispatching jobs") }}',
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
            <div class="grid grid-cols-1 @lg:grid-cols-2 @3xl:grid-cols-3 @6xl:grid-cols-4 gap-2">
                @foreach ($userRequestCounts as $userRequestCount)
                    <x-pulse::user-card wire:key="{{ $userRequestCount->user->id.$this->period }}" :name="$userRequestCount->user->name" :extra="$userRequestCount->user->extra">
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
                                <span title="{{ __('Sample rate:') }} {{ $sampleRate }}, {{ __('Raw value:') }} {{ number_format($userRequestCount->count) }}">~{{ number_format($userRequestCount->count * (1 / $sampleRate)) }}</span>
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
