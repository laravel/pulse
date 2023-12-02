<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="{{ __('Slow Requests') }}"
        title="{{ __('Time: :timems', ['time' => number_format($time)]) }}; {{ __('Run at:') }} {{ $runAt }};"
        details="{{ __(':timems threshold, past :period', ['time' => $config['threshold'], 'period' => $this->periodForHumans()]) }}"
    >
        <x-slot:icon>
            <x-pulse::icons.arrows-left-right />
        </x-slot:icon>
        <x-slot:actions>
            <x-pulse::select
                wire:model.live="orderBy"
                label="{{ __('Sort by') }}"
                :options="[
                    'slowest' => '{{ __("slowest")  }}',
                    'count' => '{{ __("count")  }}',
                ]"
                @change="loading = true"
            />
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($slowRequests->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <colgroup>
                    <col width="0%" />
                    <col width="100%" />
                    <col width="0%" />
                    <col width="0%" />
                </colgroup>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>{{ __('Method') }}</x-pulse::th>
                        <x-pulse::th>{{ __('Route') }}</x-pulse::th>
                        <x-pulse::th class="text-right">{{ __('Count') }}</x-pulse::th>
                        <x-pulse::th class="text-right">{{ __('Slowest') }}</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($slowRequests->take(100) as $slowRequest)
                        <tr class="h-2 first:h-0"></tr>
                        <tr wire:key="{{ $slowRequest->method.$slowRequest->uri.$this->period }}">
                            <x-pulse::td>
                                <x-pulse::http-method-badge :method="$slowRequest->method" />
                            </x-pulse::td>
                            <x-pulse::td class="overflow-hidden max-w-[1px]">
                                <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $slowRequest->uri }}">
                                    {{ $slowRequest->uri }}
                                </code>
                                @if ($slowRequest->action)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate" table="{{ $slowRequest->action }}">
                                        {{ $slowRequest->action }}
                                    </p>
                                @endif
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                @if ($config['sample_rate'] < 1)
                                    <span title="{{ __('Sample rate:') }} {{ $config['sample_rate'] }}, {{ __('Raw value:') }} {{ number_format($slowRequest->count) }}">~{{ number_format($slowRequest->count * (1 / $config['sample_rate'])) }}</span>
                                @else
                                    {{ number_format($slowRequest->count) }}
                                @endif
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">
                                @if ($slowRequest->slowest === null)
                                    <strong>{{ __('Unknown') }}</strong>
                                @else
                                    <strong>{{ number_format($slowRequest->slowest) ?: '<1' }}</strong> {{ __('ms') }}
                                @endif
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>

            @if ($slowRequests->count() > 100)
                <div class="mt-2 text-xs text-gray-400 text-center">{{ __('Limited to 100 entries') }}</div>
            @endif
        @endif
    </x-pulse::scroll>
</x-pulse::card>
