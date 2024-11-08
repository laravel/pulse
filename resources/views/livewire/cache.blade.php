@use('Illuminate\Support\Str')
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Cache"
        x-bind:title="`Global Time: {{ number_format($allTime) }}ms; Global run at: ${formatDate('{{ $allRunAt }}')}; Key Time: {{ number_format($keyTime) }}ms; Key run at: ${formatDate('{{ $keyRunAt }}')};`"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.rocket-launch />
        </x-slot:icon>
        <x-slot:actions>
            @php
                $count = count($config['groups']);
                $message = sprintf(
                    "Keys may be normalized using groups.\n\nThere %s currently %d %s configured.",
                    $count === 1 ? 'is' : 'are',
                    $count,
                    Str::plural('group', $count)
                );
            @endphp
            <button title="{{ $message }}" @click="alert(@js($message))">
                <x-pulse::icons.information-circle class="w-5 h-5 stroke-gray-400 dark:stroke-gray-600" />
            </button>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($allCacheInteractions->hits === 0 && $allCacheInteractions->misses === 0)
            <x-pulse::no-results />
        @else
            <div class="flex flex-col gap-6">
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="flex flex-col justify-center @sm:block">
                        <span class="text-xl uppercase font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                            @if ($config['sample_rate'] < 1)
                                <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($allCacheInteractions->hits) }}">~{{ number_format($allCacheInteractions->hits * (1 / $config['sample_rate'])) }}</span>
                            @else
                                {{ number_format($allCacheInteractions->hits) }}
                            @endif
                        </span>
                        <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-400">
                            Hits
                        </span>
                    </div>
                    <div class="flex flex-col justify-center @sm:block">
                        <span class="text-xl uppercase font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                            @if ($config['sample_rate'] < 1)
                                <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($allCacheInteractions->misses) }}">~{{ number_format(($allCacheInteractions->misses) * (1 / $config['sample_rate'])) }}</span>
                            @else
                                {{ number_format($allCacheInteractions->misses) }}
                            @endif
                        </span>
                        <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-400">
                            Misses
                        </span>
                    </div>
                    <div class="flex flex-col justify-center @sm:block">
                        <span class="text-xl uppercase font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                            {{ ((int) ($allCacheInteractions->hits / ($allCacheInteractions->hits + $allCacheInteractions->misses) * 10000)) / 100 }}%
                        </span>
                        <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-400">
                            Hit Rate
                        </span>
                    </div>
                </div>
                <div>
                    <x-pulse::table>
                        <colgroup>
                            <col width="100%" />
                            <col width="0%" />
                            <col width="0%" />
                            <col width="0%" />
                        </colgroup>
                        <x-pulse::thead>
                            <tr>
                                <x-pulse::th>Key</x-pulse::th>
                                <x-pulse::th class="text-right">Hits</x-pulse::th>
                                <x-pulse::th class="text-right">Misses</x-pulse::th>
                                <x-pulse::th class="text-right whitespace-nowrap">Hit Rate</x-pulse::th>
                            </tr>
                        </x-pulse::thead>
                        <tbody>
                            @foreach ($cacheKeyInteractions->take(100) as $interaction)
                                <tr wire:key="{{ $interaction->key }}-spacer" class="h-2 first:h-0"></tr>
                                <tr wire:key="{{ $interaction->key }}-row">
                                    <x-pulse::td class="max-w-[1px]">
                                        <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $interaction->key }}">
                                            {{ $interaction->key }}
                                        </code>
                                    </x-pulse::td>
                                    <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                        @if ($config['sample_rate'] < 1)
                                            <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($interaction->hits) }}">~{{ number_format($interaction->hits * (1 / $config['sample_rate'])) }}</span>
                                        @else
                                            {{ number_format($interaction->hits) }}
                                        @endif
                                    </x-pulse::td>
                                    <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                        @if ($config['sample_rate'] < 1)
                                            <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($interaction->misses) }}">~{{ number_format($interaction->misses * (1 / $config['sample_rate'])) }}</span>
                                        @else
                                            {{ number_format($interaction->misses) }}
                                        @endif
                                    </x-pulse::td>
                                    <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                        {{ ((int) ($interaction->hits / ($interaction->hits + $interaction->misses) * 10000)) / 100 }}%
                                    </x-pulse::td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-pulse::table>

                    @if ($cacheKeyInteractions->count() > 100)
                        <div class="mt-2 text-xs text-gray-400 text-center">Limited to 100 entries</div>
                    @endif
                </div>
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
