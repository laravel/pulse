@php
$friendlySize = function(int $mb, int $precision = 0) {
    if ($mb >= 1024 * 1024) {
        return round($mb / 1024 / 1024, $precision) . 'TB';
    }
    if ($mb >= 1024) {
        return round($mb / 1024, $precision) . 'GB';
    }
    return round($mb, $precision) . 'MB';
};
@endphp
@if ($servers)
    <div
        wire:poll.15s
        class="col-span-6"
    >
        <script wire:ignore>
            window.charts = {}
        </script>
        <div class="grid grid-cols-[max-content,_repeat(4,_auto)]">
            <div></div>
            <div></div>
            <div class="text-xs uppercase text-left text-gray-500 font-bold">Memory</div>
            <div class="text-xs uppercase text-left text-gray-500 font-bold">CPU</div>
            <div class="text-xs uppercase text-left text-gray-500 font-bold">Storage</div>
            @foreach ($servers as $slug => $server)
                @php
                    $lastReading = collect($server['readings'])->last();
                    $lastUpdated = Carbon\Carbon::parse($lastReading->date)
                @endphp
                <div class="flex items-center [&:nth-child(1n+11)]:border-t {{ count($servers) > 1 ? 'py-1' : '' }}"  title="{{ $lastUpdated->fromNow() }}">
                    @if ($lastUpdated->lt(now()->subSeconds(30)))
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 fill-red-500">
                            <path d="M2.22 2.22a.75.75 0 011.06 0l6.783 6.782a1 1 0 01.935.935l6.782 6.783a.75.75 0 11-1.06 1.06l-6.783-6.782a1 1 0 01-.935-.935L2.22 3.28a.75.75 0 010-1.06zM3.636 16.364a9.004 9.004 0 01-1.39-10.936L3.349 6.53a7.503 7.503 0 001.348 8.773.75.75 0 01-1.061 1.061zM6.464 13.536a5 5 0 01-1.213-5.103l1.262 1.262a3.493 3.493 0 001.012 2.78.75.75 0 01-1.06 1.06zM16.364 3.636a9.004 9.004 0 011.39 10.937l-1.103-1.104a7.503 7.503 0 00-1.348-8.772.75.75 0 111.061-1.061zM13.536 6.464a5 5 0 011.213 5.103l-1.262-1.262a3.493 3.493 0 00-1.012-2.78.75.75 0 011.06-1.06z" />
                        </svg>
                    @else
                        <div class="w-5 flex justify-center">
                            <div class="h-1 w-1 bg-green-500 rounded-full animate-ping"></div>
                        </div>
                    @endif
                </div>
                <div class="flex items-center [&:nth-child(1n+11)]:border-t {{ count($servers) > 1 ? 'py-1' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                    </svg>
                    <span class="text-base font-bold text-gray-600">{{ $server['name'] }}</span>
                </div>
                <div class="flex items-center gap-4 [&:nth-child(1n+11)]:border-t {{ count($servers) > 1 ? 'py-1' : '' }}">
                    <div class="w-32">
                        <span class="text-xl font-bold text-gray-700">
                            {{ $friendlySize($lastReading->memory_used, 1) }}
                        </span>
                        <span class="text-sm font-medium text-gray-500">
                            / {{ $friendlySize($lastReading->memory_total, 1) }}
                        </span>
                    </div>

                    <div wire:ignore>
                        <div id="memory-{{ $slug }}" class="h-9 w-48"></div>

                        @push('scripts')
                        <script>
                            window.charts['memory-{{ $slug }}'] = new LineChart(
                                '#memory-{{ $slug }}',
                                {
                                    series: [
                                        {
                                            className: 'stroke-purple-600',
                                            // TODO: paddedvalues
                                            data: @json(collect($server['readings'])->map(fn ($reading) => $reading->memory_used)),
                                        },
                                    ],
                                },
                                {
                                    lineSmooth: Interpolation.simple({
                                        divisor: 2
                                    }),
                                    classNames: {
                                        line: 'stroke-2 fill-none',
                                    },
                                    fullWidth: true,
                                    showPoint: false,
                                    chartPadding: 1, // Half of the stroke width - avoids overflow at the extremes
                                    axisX: {
                                        offset: 0,
                                        showGrid: false,
                                        showLabel: false,
                                    },
                                    axisY: {
                                        offset: 0,
                                        low: 0,
                                        high: {{ $lastReading->memory_total }},
                                        showGrid: false,
                                        showLabel: false,
                                    },
                                }
                            )

                            window.livewire.on('chartUpdate', (servers) => {
                                window.charts['memory-{{ $slug }}'].update({
                                    series: [
                                        {
                                            className: 'stroke-purple-600',
                                            data: servers['{{ $slug }}'].readings.map(reading => reading.memory_used),
                                        }
                                    ],
                                })
                            })
                        </script>
                        @endpush
                    </div>
                </div>
                <div class="flex items-center gap-4 [&:nth-child(1n+11)]:border-t {{ count($servers) > 1 ? 'py-1' : '' }}">
                    <div class="text-xl font-bold text-gray-700 w-12">
                        {{ $lastReading->cpu_percent }}%
                    </div>

                    <div wire:ignore>
                        <div id="cpu-{{ $slug }}" class="h-9 w-48"></div>

                        @push('scripts')
                        <script>
                            window.charts['cpu-{{ $slug }}'] = new LineChart(
                                '#cpu-{{ $slug }}',
                                {
                                    series: [
                                        {
                                            className: 'stroke-purple-600',
                                            // TODO: paddedvalues
                                            data: @json(collect($server['readings'])->map(fn ($reading) => $reading->cpu_percent)),
                                        },
                                    ],
                                },
                                {
                                    lineSmooth: Interpolation.simple({
                                        divisor: 2
                                    }),
                                    classNames: {
                                        line: 'stroke-2 fill-none',
                                    },
                                    fullWidth: true,
                                    showPoint: false,
                                    chartPadding: 1, // Half of the stroke width - avoids overflow at the extremes
                                    axisX: {
                                        offset: 0,
                                        showGrid: false,
                                        showLabel: false,
                                    },
                                    axisY: {
                                        offset: 0,
                                        low: 0,
                                        high: 100,
                                        showGrid: false,
                                        showLabel: false,
                                    },
                                }
                            )

                            // TODO: move this to a single occurrence at the bottom?
                            window.livewire.on('chartUpdate', (servers) => {
                                window.charts['cpu-{{ $slug }}'].update({
                                    series: [
                                        {
                                            className: 'stroke-purple-600',
                                            data: servers['{{ $slug }}'].readings.map(reading => reading.cpu_percent),
                                        }
                                    ],
                                })
                            })
                        </script>
                        @endpush
                    </div>
                </div>
                <div class="flex items-center gap-10 [&:nth-child(1n+11)]:border-t {{ count($servers) > 1 ? 'py-1' : '' }}">
                    @foreach ($lastReading->storage as $storage)
                        <div class="flex items-center gap-2">
                            @if (count($lastReading->storage) > 1 || $storage->directory !== '/')
                                <div>
                                    <span class="text-xl font-bold text-gray-700">{{ $storage->directory }}</span>
                                </div>
                            @endif
                            <div>
                                <span class="text-xl font-bold text-gray-700">{{ $friendlySize($storage->used) }}</span>
                                <span class="text-sm font-medium text-gray-500">/ {{ $friendlySize($storage->total) }}</span>
                            </div>

                            <div wire:ignore>
                                {{-- TODO: Make this work with multiple storage devices --}}
                                <div id="storage-{{ $slug }}" class="flex-shrink-0 w-8 h-8"></div>

                                @push('scripts')
                                <script>
                                    window.charts['storage-{{ $slug }}'] = new PieChart(
                                        '#storage-{{ $slug }}',
                                        {
                                            series: [
                                                { value: {{ $storage->total - $storage->used }}, className: 'stroke-purple-100', },
                                                { value: {{ $storage->used }}, className: 'stroke-purple-600' },
                                            ],
                                        },
                                        {
                                            donut: true,
                                            donutWidth: 4,
                                            showLabel: false,
                                        }
                                    )

                                    // TODO: Live update the chart
                                </script>
                                @endpush
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
@endif
