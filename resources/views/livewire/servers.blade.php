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

$cols = ! empty($cols) ? $cols : 'full';
$rows = ! empty($rows) ? $rows : 1;
@endphp

<section
    wire:poll.5s
    class="overflow-x-auto pb-px default:col-span-full default:lg:col-span-{{ $cols }} default:row-span-{{ $rows }} {{ $class }}"
    x-data="{
        loadingNewDataset: false,
        init() {
            Livewire.on('period-changed', () => (this.loadingNewDataset = true))

            Livewire.hook('commit', ({ component, succeed }) => {
                if (component.name === 'servers') {
                    succeed(() => this.loadingNewDataset = false)
                }
            })
        },
    }"
>
    @if ($servers->count() > 0)
        <div class="min-w-[42rem] grid grid-cols-[max-content,minmax(max-content,1fr),max-content,minmax(0,2fr),max-content,minmax(0,2fr),minmax(max-content,1fr)]">
            <div></div>
            <div></div>
            <div class="text-xs uppercase text-left text-gray-500 dark:text-gray-400 font-bold">CPU</div>
            <div></div>
            <div class="text-xs uppercase text-left text-gray-500 dark:text-gray-400 font-bold">Memory</div>
            <div></div>
            <div class="text-xs uppercase text-left text-gray-500 dark:text-gray-400 font-bold">Storage</div>
            @foreach ($servers as $server)
                <div wire:key="{{ $server->name }}" class="flex items-center [&:nth-child(1n+15)]:border-t {{ count($servers) > 1 ? 'py-2' : '' }}" :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''" title="{{ $server->updated_at->fromNow() }}">
                    @if ($server->recently_reported)
                        <div class="w-5 flex justify-center mr-1">
                            <div class="h-1 w-1 bg-green-500 rounded-full animate-ping"></div>
                        </div>
                    @else
                        <x-pulse::icons.signal-slash class="w-5 h-5 stroke-red-500 mr-1" />
                    @endif
                </div>
                <div class="flex items-center pr-8 xl:pr-12 [&:nth-child(1n+15)]:border-t {{ count($servers) > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}" :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500 dark:stroke-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                    </svg>
                    <span class="text-base font-bold text-gray-600 dark:text-gray-300">{{ $server->name }}</span>
                </div>
                <div class="flex items-center [&:nth-child(1n+15)]:border-t {{ count($servers) > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}" :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    <div class="text-xl font-bold text-gray-700 dark:text-gray-200 w-14 whitespace-nowrap tabular-nums">
                        {{ $server->cpu_percent }}%
                    </div>
                </div>
                <div class="flex items-center pr-8 xl:pr-12 [&:nth-child(1n+15)]:border-t {{ count($servers) > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}" :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    <div
                        wire:ignore
                        class="w-full max-w-xs h-9 relative"
                        x-data="{
                            init() {
                                let chart = new Chart(
                                    this.$refs.canvas,
                                    {
                                        type: 'line',
                                        data: {
                                            labels: @js(collect($server->readings)->pluck('date')),
                                            datasets: [
                                                {
                                                    label: 'CPU Percent',
                                                    borderColor: '#9333ea',
                                                    borderWidth: 2,
                                                    borderCapStyle: 'round',
                                                    data: @js(collect($server->readings)->pluck('cpu_percent')),
                                                    pointStyle: false,
                                                    tension: 0.2,
                                                    spanGaps: false,
                                                },
                                            ],
                                        },
                                        options: {
                                            maintainAspectRatio: false,
                                            layout: {
                                                autoPadding: false,
                                            },
                                            scales: {
                                                x: {
                                                    display: false,
                                                    grid: {
                                                        display: false,
                                                    },
                                                },
                                                y: {
                                                    display: false,
                                                    min: 0,
                                                    max: 100,
                                                    grid: {
                                                        display: false,
                                                    },
                                                },
                                            },
                                            plugins: {
                                                legend: {
                                                    display: false,
                                                },
                                                tooltip: {
                                                    callbacks: {
                                                        title: () => '',
                                                        label: (context) => `${context.label} - ${context.formattedValue}%`
                                                    },
                                                    displayColors: false,
                                                },
                                            },
                                        },
                                    }
                                )

                                Livewire.on('chart-update', ({ servers }) => {
                                    // TODO: Figure out how to destroy the Alpine instance and remove this listener.

                                    if (chart === undefined) {
                                        return
                                    }

                                    if (servers['{{ $server->slug }}'] === undefined && chart) {
                                        chart.destroy()
                                        chart = undefined
                                        return
                                    }

                                    chart.data.labels = servers['{{ $server->slug }}'].readings.map(reading => reading.date)
                                    chart.data.datasets[0].data = servers['{{ $server->slug }}'].readings.map(reading => reading.cpu_percent)
                                    chart.update()
                                })
                            }
                        }"
                    >
                        <canvas x-ref="canvas" class="w-full ring-1 ring-gray-900/5 bg-white dark:bg-gray-900 rounded-md shadow-sm"></canvas>
                    </div>
                </div>
                <div class="flex items-center [&:nth-child(1n+15)]:border-t {{ count($servers) > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}" :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    <div class="w-36 flex-shrink-0 whitespace-nowrap tabular-nums">
                        <span class="text-xl font-bold text-gray-700 dark:text-gray-200">
                            {{ $friendlySize($server->memory_used, 1) }}
                        </span>
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            / {{ $friendlySize($server->memory_total, 1) }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center pr-8 xl:pr-12 [&:nth-child(1n+15)]:border-t {{ count($servers) > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}" :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    <div
                        wire:ignore
                        class="w-full max-w-xs h-9 relative"
                        x-data="{
                            init() {
                                let chart = new Chart(
                                    this.$refs.canvas,
                                    {
                                        type: 'line',
                                        data: {
                                            labels: @js(collect($server->readings)->pluck('date')),
                                            datasets: [
                                                {
                                                    label: 'Memory Used',
                                                    borderColor: '#9333ea',
                                                    borderWidth: 2,
                                                    borderCapStyle: 'round',
                                                    data: @js(collect($server->readings)->pluck('memory_used')),
                                                    pointStyle: false,
                                                    tension: 0.2,
                                                    spanGaps: false,
                                                },
                                            ],
                                        },
                                        options: {
                                            maintainAspectRatio: false,
                                            layout: {
                                                autoPadding: false,
                                            },
                                            scales: {
                                                x: {
                                                    display: false,
                                                    grid: {
                                                        display: false,
                                                    },
                                                },
                                                y: {
                                                    display: false,
                                                    min: 0,
                                                    max: {{ $server->memory_total }},
                                                    grid: {
                                                        display: false,
                                                    },
                                                },
                                            },
                                            plugins: {
                                                legend: {
                                                    display: false,
                                                },
                                                tooltip: {
                                                    callbacks: {
                                                        title: () => '',
                                                        label: (context) => `${context.label} - ${context.formattedValue} MB`
                                                    },
                                                    displayColors: false,
                                                },
                                            },
                                        },
                                    }
                                )

                                Livewire.on('chart-update', ({ servers }) => {
                                    // TODO: Figure out how to destroy the Alpine instance and remove this listener.

                                    if (chart === undefined) {
                                        return
                                    }

                                    if (servers['{{ $server->slug }}'] === undefined && chart) {
                                        chart.destroy()
                                        chart = undefined
                                        return
                                    }

                                    chart.data.labels = servers['{{ $server->slug }}'].readings.map(reading => reading.date)
                                    chart.data.datasets[0].data = servers['{{ $server->slug }}'].readings.map(reading => reading.memory_used)
                                    chart.update()
                                })
                            }
                        }"
                    >
                        <canvas x-ref="canvas" class="w-full ring-1 ring-gray-900/5 bg-white dark:bg-gray-900 rounded-md shadow-sm"></canvas>
                    </div>
                </div>
                <div class="flex items-center gap-8 [&:nth-child(1n+15)]:border-t {{ count($servers) > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    @foreach ($server->storage as $storage)
                        <div class="flex items-center gap-4" title="Directory: {{ $storage->directory }}">
                            <div class="whitespace-nowrap tabular-nums">
                                <span class="text-xl font-bold text-gray-700 dark:text-gray-200">{{ $friendlySize($storage->used) }}</span>
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">/ {{ $friendlySize($storage->total) }}</span>
                            </div>

                            <div
                                wire:ignore
                                x-data="{
                                    init() {
                                        let chart = new Chart(
                                            this.$refs.canvas,
                                            {
                                                type: 'doughnut',
                                                data: {
                                                    labels: ['Used', 'Free'],
                                                    datasets: [
                                                        {
                                                            data: [
                                                                {{ $storage->used }},
                                                                {{ $storage->total - $storage->used }},
                                                            ],
                                                            backgroundColor: [
                                                                '#9333ea',
                                                                '#c084fc30',
                                                            ],
                                                            hoverBackgroundColor: [
                                                                '#9333ea',
                                                                '#c084fc30',
                                                            ],
                                                        },
                                                    ],
                                                },
                                                options: {
                                                    borderWidth: 0,
                                                    plugins: {
                                                        legend: {
                                                            display: false,
                                                        },
                                                        tooltip: {
                                                            enabled: false,
                                                            callbacks: {
                                                                label: (context) => context.formattedValue + ' MB',
                                                            },
                                                            displayColors: false,
                                                        },
                                                    },
                                                },
                                            }
                                        )

                                        Livewire.on('chart-update', ({ servers }) => {
                                            // TODO: Figure out how to destroy the Alpine instance and remove this listener.

                                            const storage = servers['{{ $server->slug }}']?.storage?.find(storage => storage.directory === '{{ $storage->directory }}')

                                            if (chart === undefined) {
                                                return
                                            }

                                            if (storage === undefined && chart) {
                                                chart.destroy()
                                                chart = undefined
                                                return
                                            }

                                            chart.data.datasets[0].data = [
                                                storage.used,
                                                storage.total - storage.used,
                                            ]
                                            chart.update()
                                        })
                                    }
                                }"
                            >
                                <canvas x-ref="canvas" class="h-8 w-8"></canvas>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</section>
