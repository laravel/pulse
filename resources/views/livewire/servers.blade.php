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
    x-data="{
        loading: false,
        init() {
            Livewire.hook('commit', ({ component, succeed }) => {
                if (component.id === $wire.__instance.id) {
                    succeed(() => this.loading = false)
                }
            })
        },
    }"
    class="overflow-x-auto pb-px default:col-span-full default:lg:col-span-{{ $cols }} default:row-span-{{ $rows }} {{ $class }}"
    :class="loading && 'opacity-25 animate-pulse'"
>
    @if ($servers->isNotEmpty())
        <div class="grid grid-cols-[max-content,minmax(max-content,1fr),max-content,minmax(min-content,2fr),max-content,minmax(min-content,2fr),minmax(max-content,1fr)]">
            <div></div>
            <div></div>
            <div class="text-xs uppercase text-left text-gray-500 dark:text-gray-400 font-bold">CPU</div>
            <div></div>
            <div class="text-xs uppercase text-left text-gray-500 dark:text-gray-400 font-bold">Memory</div>
            <div></div>
            <div class="text-xs uppercase text-left text-gray-500 dark:text-gray-400 font-bold">Storage</div>
            @foreach ($servers as $slug => $server)
                <div wire:key="{{ $slug }}-indicator" class="flex items-center {{ $servers->count() > 1 ? 'py-2' : '' }}" title="{{ $server->updated_at->fromNow() }}">
                    @if ($server->recently_reported)
                        <div class="w-5 flex justify-center mr-1">
                            <div class="h-1 w-1 bg-green-500 rounded-full animate-pulse"></div>
                        </div>
                    @else
                        <x-pulse::icons.signal-slash class="w-5 h-5 stroke-red-500 mr-1" />
                    @endif
                </div>
                <div wire:key="{{ $slug }}-name" class="flex items-center pr-8 xl:pr-12 {{ $servers->count() > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    <x-pulse::icons.server class="w-6 h-6 mr-2 stroke-gray-500 dark:stroke-gray-400" />
                    <span class="text-base font-bold text-gray-600 dark:text-gray-300" x-bind:title="`Time: {{ number_format($time) }}ms; Run at: ${formatDate('{{ $runAt }}')};`">{{ $server->name }}</span>
                </div>
                <div wire:key="{{ $slug }}-cpu" class="flex items-center {{ $servers->count() > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    <div class="text-xl font-bold text-gray-700 dark:text-gray-200 w-14 whitespace-nowrap tabular-nums">
                        {{ $server->cpu_current }}%
                    </div>
                </div>
                <div wire:key="{{ $slug }}-cpu-graph" class="flex items-center pr-8 xl:pr-12 {{ $servers->count() > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    <div
                        wire:ignore
                        class="w-full min-w-[5rem] max-w-xs h-9 relative"
                        x-data="cpuChart({
                            slug: '{{ $slug }}',
                            labels: @js($server->cpu->keys()),
                            data: @js($server->cpu->values()),
                        })"
                    >
                        <canvas x-ref="canvas" class="w-full ring-1 ring-gray-900/5 bg-white dark:bg-gray-900 rounded-md shadow-sm"></canvas>
                    </div>
                </div>
                <div wire:key="{{ $slug }}-memory" class="flex items-center {{ $servers->count() > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    <div class="w-36 flex-shrink-0 whitespace-nowrap tabular-nums">
                        <span class="text-xl font-bold text-gray-700 dark:text-gray-200">
                            {{ $friendlySize($server->memory_current, 1) }}
                        </span>
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            / {{ $friendlySize($server->memory_total, 1) }}
                        </span>
                    </div>
                </div>
                <div wire:key="{{ $slug }}-memory-graph" class="flex items-center pr-8 xl:pr-12 {{ $servers->count() > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    <div
                        wire:ignore
                        class="w-full min-w-[5rem] max-w-xs h-9 relative"
                        x-data="memoryChart({
                            slug: '{{ $slug }}',
                            labels: @js($server->memory->keys()),
                            data: @js($server->memory->values()),
                            total: @js($server->memory_total),
                        })"
                    >
                        <canvas x-ref="canvas" class="w-full ring-1 ring-gray-900/5 bg-white dark:bg-gray-900 rounded-md shadow-sm"></canvas>
                    </div>
                </div>
                <div wire:key="{{ $slug }}-storage" class="flex items-center gap-8 {{ $servers->count() > 1 ? 'py-2' : '' }} {{ ! $server->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    @foreach ($server->storage as $storage)
                        <div wire:key="{{ $slug.'-storage-'.$storage->directory }}" class="flex items-center gap-4" title="Directory: {{ $storage->directory }}">
                            <div class="whitespace-nowrap tabular-nums">
                                <span class="text-xl font-bold text-gray-700 dark:text-gray-200">{{ $friendlySize($storage->used) }}</span>
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">/ {{ $friendlySize($storage->total) }}</span>
                            </div>

                            <div
                                wire:ignore
                                x-data="storageChart({
                                    slug: '{{ $slug }}',
                                    directory: '{{ $storage->directory }}',
                                    used: {{ $storage->used }},
                                    total: {{ $storage->total }},
                                })"
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

@script
<script>
Alpine.data('cpuChart', (config) => ({
    init() {
        let chart = new Chart(
            this.$refs.canvas,
            {
                type: 'line',
                data: {
                    labels: config.labels.map(formatDate),
                    datasets: [
                        {
                            label: 'CPU Percent',
                            borderColor: '#9333ea',
                            borderWidth: 2,
                            borderCapStyle: 'round',
                            data: config.data,
                            pointHitRadius: 10,
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
                            mode: 'index',
                            position: 'nearest',
                            intersect: false,
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

        Livewire.on('servers-chart-update', ({ servers }) => {
            if (chart === undefined) {
                return
            }

            if (servers[config.slug] === undefined && chart) {
                chart.destroy()
                chart = undefined
                return
            }

            chart.data.labels = Object.keys(servers[config.slug].cpu).map(formatDate)
            chart.data.datasets[0].data = Object.values(servers[config.slug].cpu)
            chart.update()
        })
    }
}))

Alpine.data('memoryChart', (config) => ({
    init() {
        let chart = new Chart(
            this.$refs.canvas,
            {
                type: 'line',
                data: {
                    labels: config.labels.map(formatDate),
                    datasets: [
                        {
                            label: 'Memory Used',
                            borderColor: '#9333ea',
                            borderWidth: 2,
                            borderCapStyle: 'round',
                            data: config.data,
                            pointHitRadius: 10,
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
                            max: config.total,
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
                            mode: 'index',
                            position: 'nearest',
                            intersect: false,
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

        Livewire.on('servers-chart-update', ({ servers }) => {
            if (chart === undefined) {
                return
            }

            if (servers[config.slug] === undefined && chart) {
                chart.destroy()
                chart = undefined
                return
            }

            chart.data.labels = Object.keys(servers[config.slug].memory).map(formatDate)
            chart.data.datasets[0].data = Object.values(servers[config.slug].memory)
            chart.update()
        })
    }
}))

Alpine.data('storageChart', (config) => ({
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
                                config.used,
                                config.total - config.used,
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

        Livewire.on('servers-chart-update', ({ servers }) => {
            const storage = servers[config.slug]?.storage?.find(storage => storage.directory === config.directory)

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
}))
</script>
@endscript
