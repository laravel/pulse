@if ($servers)
    <?php
    function friendly_size($mb) {
        if ($mb >= 1000000) {
            return $mb / 1000000 . "TB";
        }
        if ($mb >= 1000) {
            return round($mb / 1000) . "GB";
        }
        return $mb + "MB";
    }
    ?>
    <div
        wire:poll
        class="col-span-6"
    >
        <script wire:ignore>
            window.charts = {}
        </script>
        <div class="grid grid-cols-[repeat(4,_auto),_max-content]">
            <div></div>
            <div class="text-xs uppercase text-left text-gray-500 font-bold">Memory</div>
            <div class="text-xs uppercase text-left text-gray-500 font-bold">CPU</div>
            <div class="text-xs uppercase text-left text-gray-500 font-bold">Storage</div>
            <div></div>
            @foreach ($servers as $slug => $server)
                @php
                    $lastReading = collect($server['readings'])->last();
                @endphp
                <div class="flex items-center [&:nth-child(1n+11)]:border-t {{ count($servers) > 1 ? 'py-1' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                    </svg>
                    <span class="text-base font-bold text-gray-600">{{ $server['name'] }}</span>
                </div>
                <div class="flex items-center gap-4 [&:nth-child(1n+11)]:border-t {{ count($servers) > 1 ? 'py-1' : '' }}">
                    <div class="w-32">
                        <span class="text-base font-bold text-gray-700">
                            {{ round($lastReading['memory_used'] / 1000 / 1000, 1) }}GB
                        </span>
                        <span class="text-sm font-medium text-gray-500">
                            / {{ round($lastReading['memory_total'] / 1000 / 1000, 1) }}GB
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
                                            className: 'stroke-green-500',
                                            // TODO: paddedvalues
                                            data: @json(collect($server['readings'])->map(fn ($reading) => $reading['memory_used'])),
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
                                        high: {{ $lastReading['memory_total'] }},
                                        showGrid: false,
                                        showLabel: false,
                                    },
                                }
                            )

                            window.livewire.on('chartUpdate', (servers) => {
                                window.charts['memory-{{ $slug }}'].update({
                                    series: [
                                        {
                                            className: 'stroke-green-500',
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
                    <div class="text-base font-bold text-gray-700 w-12">
                        {{ $lastReading['cpu'] }}%
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
                                            className: 'stroke-green-500',
                                            // TODO: paddedvalues
                                            data: @json(collect($server['readings'])->map(fn ($reading) => $reading['cpu'])),
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
                                            className: 'stroke-green-500',
                                            data: servers['{{ $slug }}'].readings.map(reading => reading.cpu),
                                        }
                                    ],
                                })
                            })
                        </script>
                        @endpush
                    </div>
                </div>
                <div class="flex items-center gap-10 [&:nth-child(1n+11)]:border-t {{ count($servers) > 1 ? 'py-1' : '' }}">
                    @foreach ($lastReading['storage'] as $storage)
                        <div class="flex items-center gap-2">
                            @if (count($lastReading['storage']) > 1 || $storage->directory !== '/')
                                <div>
                                    <span class="text-base font-bold text-gray-700">{{ $storage->directory }}</span>
                                </div>
                            @endif
                            <div>
                                <span class="text-base font-bold text-gray-700">{{ friendly_size($storage->used) }}</span>
                                <span class="text-sm font-medium text-gray-500">/ {{ friendly_size($storage->total) }}</span>
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
                <div class="[&:nth-child(1n+11)]:border-t flex items-center justify-end {{ count($servers) > 1 ? 'py-1' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 fill-green-500">
                        <path d="M16.364 3.636a.75.75 0 00-1.06 1.06 7.5 7.5 0 010 10.607.75.75 0 001.06 1.061 9 9 0 000-12.728zM4.697 4.697a.75.75 0 00-1.061-1.06 9 9 0 000 12.727.75.75 0 101.06-1.06 7.5 7.5 0 010-10.607z" />
                        <path d="M12.475 6.465a.75.75 0 011.06 0 5 5 0 010 7.07.75.75 0 11-1.06-1.06 3.5 3.5 0 000-4.95.75.75 0 010-1.06zM7.525 6.465a.75.75 0 010 1.06 3.5 3.5 0 000 4.95.75.75 0 01-1.06 1.06 5 5 0 010-7.07.75.75 0 011.06 0zM11 10a1 1 0 11-2 0 1 1 0 012 0z" />
                    </svg>
                </div>
            @endforeach
        </div>
    </div>
@endif
