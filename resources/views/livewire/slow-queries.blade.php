@php
use \Doctrine\SqlFormatter\HtmlHighlighter;
use \Doctrine\SqlFormatter\SqlFormatter;

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
@endphp
<x-pulse::card class="col-span-{{ $cols }}">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500 dark:stroke-gray-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
            <span>
                <span title="Time: {{ $time }}ms; Run at: {{ $runAt }}">Slow Queries</span>
                <small class="ml-2 text-gray-400 dark:text-gray-600 text-xs font-medium">{{ config('pulse.slow_query_threshold') }}ms threshold, past {{ $this->periodForHumans() }}</small>
            </span>
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <div x-data="{
            loadingNewDataset: false,
            init() {
                Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                Livewire.hook('commit', ({ component, succeed }) => {
                    if (component.name === 'slow-queries') {
                        succeed(() => this.loadingNewDataset = false)
                    }
                })
            }
        }">
            <div>
                <div :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    @if (count($slowQueries) === 0)
                        <x-pulse::no-results />
                    @else
                        <x-pulse::table class="table-fixed">
                            <x-pulse::thead>
                                <tr>
                                    <x-pulse::th class="text-left">Query</x-pulse::th>
                                    <x-pulse::th class="text-right w-24">Count</x-pulse::th>
                                    <x-pulse::th class="text-right w-24">Slowest</x-pulse::th>
                                </tr>
                            </x-pulse::thead>
                            <tbody>
                                @foreach ($slowQueries as $query)
                                    <tr>
                                        <x-pulse::td class="!p-0">
                                            <div class="relative">
                                                <code class="bg-gray-700 dark:bg-gray-700/50 py-3 rounded-md h-full text-gray-100 block text-xs whitespace-nowrap overflow-x-auto [scrollbar-color:theme(colors.gray.500)_transparent] [scrollbar-width:thin]">
                                                    <span class="px-3">{!! $sqlFormatter->highlight($query->sql) !!}</span>
                                                </code>
                                                <div class="absolute top-0 right-0 bottom-0 rounded-r-md w-3 bg-gradient-to-r from-transparent to-gray-700 dark:to-[#2B3544] pointer-events-none"></div>
                                            </div>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm w-24 tabular-nums">
                                            <strong>{{ number_format($query->count) }}</strong>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm w-24 whitespace-nowrap tabular-nums">
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
                </div>
            </div>
        </div>
    </div>
</x-pulse::card>
