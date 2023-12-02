<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="{{ __('Slow Jobs') }}"
        title="{{ __('Time: :timems', ['time' => number_format($time)]) }}; {{ __('Run at:') }} {{ $runAt }};"
        details="{{ __(':timems threshold, past :period', ['time' => $config['threshold'], 'period' => $this->periodForHumans()]) }}"
    >
        <x-slot:icon>
            <x-pulse::icons.command-line />
        </x-slot:icon>
        <x-slot:actions>
            <x-pulse::select
                wire:model.live="orderBy"
                label="{{ __('Sort by') }}"
                :options="[
                    'slowest' => '{{ __("slowest") }}',
                    'count' => '{{ __("count") }}',
                ]"
                @change="loading = true"
            />
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($slowJobs->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <colgroup>
                    <col width="100%" />
                    <col width="0%" />
                    <col width="0%" />
                </colgroup>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>{{ __('Job') }}</x-pulse::th>
                        <x-pulse::th class="text-right">{{ __('Count') }}</x-pulse::th>
                        <x-pulse::th class="text-right">{{ __('Slowest') }}</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($slowJobs->take(100) as $job)
                        <tr class="h-2 first:h-0"></tr>
                        <tr wire:key="{{ $job->job.$this->period }}">
                            <x-pulse::td class="max-w-[1px]">
                                <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $job->job }}">
                                    {{ $job->job }}
                                </code>
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                @if ($config['sample_rate'] < 1)
                                    <span title="{{ __('Sample rate:') }} {{ $config['sample_rate'] }}, {{ __('Raw value:') }} {{ number_format($job->count) }}">~{{ number_format($job->count * (1 / $config['sample_rate'])) }}</span>
                                @else
                                    {{ number_format($job->count) }}
                                @endif
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">
                                @if ($job->slowest === null)
                                    <strong>{{ __('Unknown') }}</strong>
                                @else
                                    <strong>{{ number_format($job->slowest) ?: '<1' }}</strong> {{ __('ms') }}
                                @endif
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif

        @if ($slowJobs->count() > 100)
            <div class="mt-2 text-xs text-gray-400 text-center">{{ __('Limited to 100 entries') }}</div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
