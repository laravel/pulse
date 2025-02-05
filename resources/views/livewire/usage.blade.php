<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        :name="match ($this->type) {
            'requests' => 'Top 10 Users Making Requests',
            'slow_requests' => 'Top 10 Users Experiencing Slow Endpoints',
            'jobs' => 'Top 10 Users Dispatching Jobs',
            default => 'Application Usage'
        }"
        x-bind:title="`Time: {{ number_format($time) }}ms; Run at: ${formatDate('{{ $runAt }}')};`"
        details="{{ $this->usage === 'slow_requests' ? (is_array($slowRequestsConfig['threshold']) ? '' : $slowRequestsConfig['threshold'].'ms threshold, ') : '' }}past {{ $this->periodForHumans() }}"
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
                    id="select-usage-by"
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
            @if ($this->usage === 'slow_requests' && is_array($slowRequestsConfig['threshold']))
                @php
                    $message = 'You have per-route thresholds configured.';
                @endphp
                <button title="{{ $message }}" @click="alert(@js($message))">
                    <x-pulse::icons.information-circle class="w-5 h-5 stroke-gray-400 dark:stroke-gray-600" />
                </button>
            @endif
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($userRequestCounts->isEmpty())
            <x-pulse::no-results />
        @else
            <div class="grid grid-cols-1 @lg:grid-cols-2 @3xl:grid-cols-3 @6xl:grid-cols-4 gap-2">
                @php
                    $sampleRate = match($this->usage) {
                        'requests' => $userRequestsConfig['sample_rate'],
                        'slow_requests' => $slowRequestsConfig['sample_rate'],
                        'jobs' => $jobsConfig['sample_rate'],
                    };
                @endphp

                @foreach ($userRequestCounts as $userRequestCount)
                    <x-pulse::user-card wire:key="{{ $userRequestCount->key }}" :user="$userRequestCount->user">
                        <x-slot:stats>
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
