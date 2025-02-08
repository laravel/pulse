@php
use \Doctrine\SqlFormatter\HtmlHighlighter;
use \Doctrine\SqlFormatter\SqlFormatter;

if ($this->wantsHighlighting()) {
    $sqlFormatter = new SqlFormatter(new HtmlHighlighter([
        HtmlHighlighter::HIGHLIGHT_RESERVED => 'class="font-semibold"',
        HtmlHighlighter::HIGHLIGHT_QUOTE => 'class="text-purple-200"',
        HtmlHighlighter::HIGHLIGHT_BACKTICK_QUOTE => 'class="text-purple-200"',
        HtmlHighlighter::HIGHLIGHT_BOUNDARY => 'class="text-cyan-200"',
        HtmlHighlighter::HIGHLIGHT_NUMBER => 'class="text-orange-200"',
        HtmlHighlighter::HIGHLIGHT_WORD => 'class="text-orange-200"',
        HtmlHighlighter::HIGHLIGHT_VARIABLE => 'class="text-orange-200"',
        HtmlHighlighter::HIGHLIGHT_ERROR => 'class="text-red-200"',
        HtmlHighlighter::HIGHLIGHT_COMMENT => 'class="text-gray-400"',
    ], false));
}
@endphp
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Slow Queries"
        x-bind:title="`Time: {{ number_format($time) }}ms; Run at: ${formatDate('{{ $runAt }}')};`"
        details="{{ is_array($config['threshold']) ? '' : $config['threshold'].'ms threshold, ' }}past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.circle-stack />
        </x-slot:icon>
        <x-slot:actions>
            <x-pulse::select
                wire:model.live="orderBy"
                id="select-slow-queries-order-by"
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
        @if ($slowQueries->isEmpty())
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
                        <x-pulse::th>Query</x-pulse::th>
                        <x-pulse::th class="text-right">Count</x-pulse::th>
                        <x-pulse::th class="text-right">Slowest</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($slowQueries->take(100) as $query)
                        <tr wire:key="{{ md5($query->sql.$query->location) }}-spacer" class="h-2 first:h-0"></tr>
                        <tr wire:key="{{ md5($query->sql.$query->location) }}-row">
                            <x-pulse::td class="!p-0 truncate max-w-[1px]">
                                <div class="relative">
                                    <div class="bg-gray-700 dark:bg-gray-800 py-4 rounded-md text-gray-100 block text-xs whitespace-nowrap overflow-x-auto [scrollbar-color:theme(colors.gray.500)_transparent] [scrollbar-width:thin]">
                                        <code class="px-3">{!! $this->wantsHighlighting() ? $sqlFormatter->highlight($query->sql) : e($query->sql)  !!}</code>
                                        @if ($query->location)
                                            <p class="px-3 mt-3 text-xs leading-none text-gray-400 dark:text-gray-500">
                                                {{ $query->location }}
                                            </p>
                                        @endif
                                        @if (is_array($config['threshold']))
                                            <p class="px-3 mt-3 text-xs leading-none text-gray-400 dark:text-gray-500">
                                                {{ $query->threshold }}ms threshold
                                            </p>
                                        @endif
                                    </div>
                                    <div class="absolute top-0 right-0 bottom-0 rounded-r-md w-3 bg-gradient-to-r from-transparent to-gray-700 dark:to-gray-800 pointer-events-none"></div>
                                </div>
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                @if ($config['sample_rate'] < 1)
                                    <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($query->count) }}">~{{ number_format($query->count * (1 / $config['sample_rate'])) }}</span>
                                @else
                                    {{ number_format($query->count) }}
                                @endif
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">
                                @if ($query->slowest === null)
                                    <strong>Unknown</strong>
                                @else
                                    <strong>{{ number_format($query->slowest) ?: '<1' }}</strong> ms
                                @endif
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif

        @if ($slowQueries->count() > 100)
            <div class="mt-2 text-xs text-gray-400 text-center">Limited to 100 entries</div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
