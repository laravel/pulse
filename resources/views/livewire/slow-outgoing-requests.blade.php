@php
    use Illuminate\Support\Str;
@endphp
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="{{ __('Slow Outgoing Requests') }}"
        title="{{ __('Time: :timems', ['time' => number_format($time)]) }}; {{ __('Run at:') }} {{ $runAt }};"
        details="{{ __(':timems threshold, past :period', ['time' => $config['threshold'], 'period' => $this->periodForHumans()]) }}"
    >
        <x-slot:icon>
            <x-pulse::icons.cloud-arrow-up />
        </x-slot:icon>
        <x-slot:actions>
            @php
                $count = count($config['groups']);
                $message = trans_choice(
                    '{1} URIs may be normalized using groups.\n\nThere is currently :count group configured.|' .
                    '{2,*} URIs may be normalized using groups.\n\nThere are currently :count groups configured.',
                    $count,
                    compact('count')
                );
            @endphp
            <button title="{{ $message }}" @click="alert('{{ str_replace("\n", '\n', $message) }}')">
                <x-pulse::icons.information-circle class="w-5 h-5 stroke-gray-400 dark:stroke-gray-600" />
            </button>

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
        @if (! $supported)
            <div class="h-full flex flex-col items-center justify-center p-4 py-6">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-full text-xs leading-none px-2 py-1 text-gray-500 dark:text-gray-400">
                    {{ __('Requires laravel/framework :version', ['version' => 'v10.14+']) }}
                </div>
            </div>
        @else
            @if ($slowOutgoingRequests->isEmpty())
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
                            <x-pulse::th>{{ __('URI') }}</x-pulse::th>
                            <x-pulse::th class="text-right">{{ __('Count') }}</x-pulse::th>
                            <x-pulse::th class="text-right">{{ __('Slowest') }}</x-pulse::th>
                        </tr>
                    </x-pulse::thead>
                    <tbody>
                        @foreach ($slowOutgoingRequests->take(100) as $request)
                            <tr class="h-2 first:h-0"></tr>
                            <tr wire:key="{{ $request->method.$request->uri.$this->period }}">
                                <x-pulse::td>
                                    <x-pulse::http-method-badge :method="$request->method" />
                                </x-pulse::td>
                                <x-pulse::td class="max-w-[1px]">
                                    <div class="flex items-center" title="{{ $request->uri }}">
                                        @if ($host = parse_url($request->uri, PHP_URL_HOST))
                                            <img wire:ignore src="https://unavatar.io/{{ $host }}?fallback=false" loading="lazy" class="w-4 h-4 mr-2" onerror="this.style.display='none'" />
                                        @endif
                                        <code class="block text-xs text-gray-900 dark:text-gray-100 truncate">
                                            {{ $request->uri }}
                                        </code>
                                    </div>
                                </x-pulse::td>
                                <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                    @if ($config['sample_rate'] < 1)
                                        <span title="{{ __('Sample rate:') }} {{ $config['sample_rate'] }}, {{ __('Raw value:') }} {{ number_format($request->count) }}">~{{ number_format($request->count * (1 / $config['sample_rate'])) }}</span>
                                    @else
                                        {{ number_format($request->count) }}
                                    @endif
                                </x-pulse::td>
                                <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">
                                    @if ($request->slowest === null)
                                        <strong>{{ __('Unknown') }}</strong>
                                    @else
                                        <strong>{{ number_format($request->slowest) ?: '<1' }}</strong> {{ __('ms') }}
                                    @endif
                                </x-pulse::td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-pulse::table>

                @if ($slowOutgoingRequests->count() > 100)
                    <div class="mt-2 text-xs text-gray-400 text-center">{{ __('Limited to 100 entries') }}</div>
                @endif
            @endif
        @endif
    </x-pulse::scroll>
</x-pulse::card>
