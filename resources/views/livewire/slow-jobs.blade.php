<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Slow Jobs"
        x-bind:title="`Time: {{ number_format($time, 0) }}ms; Run at: ${formatDate('{{ $runAt }}')};`"
        details="{{ is_array($config['threshold']) ? '' : $config['threshold'].'ms threshold, ' }}past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.command-line />
        </x-slot:icon>
        <x-slot:actions>
            <x-pulse::select
                wire:model.live="orderBy"
                id="select-slow-jobs-order-by"
                label="Sort by"
                :options="[
                    'slowest' => 'slowest',
                    'count' => 'count',
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
                        <x-pulse::th>Job</x-pulse::th>
                        <x-pulse::th class="text-right">Count</x-pulse::th>
                        <x-pulse::th class="text-right">Slowest</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($slowJobs->take(100) as $job)
                        <tr wire:key="{{ $job->job }}-spacer" class="h-2 first:h-0"></tr>
                        <tr wire:key="{{ $job->job }}-row">
                            <x-pulse::td class="max-w-[1px]">
                                <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $job->job }}">
                                    {{ $job->job }}
                                </code>
                                @if (is_array($config['threshold']))
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $job->threshold }}ms threshold
                                    </p>
                                @endif
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                @if ($config['sample_rate'] < 1)
                                    <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($job->count) }}">~{{ number_format($job->count * (1 / $config['sample_rate'])) }}</span>
                                @else
                                    {{ number_format($job->count) }}
                                @endif
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">
                                @if ($job->slowest === null)
                                    <strong>Unknown</strong>
                                @else
                                    <strong>{{ number_format($job->slowest) ?: '<1' }}</strong> ms
                                @endif
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif

        @if ($slowJobs->count() > 100)
            <div class="mt-2 text-xs text-gray-400 text-center">Limited to 100 entries</div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
