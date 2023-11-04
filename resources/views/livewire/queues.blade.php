<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Queues"
        title="Time: {{ $time }}; Run at: {{ $runAt }};"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.queue-list />
        </x-slot:icon>
        <x-slot:actions>
            <div class="flex flex-wrap gap-4">
                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 font-medium">
                    <div class="h-0.5 w-3 rounded-full bg-[rgba(107,114,128,0.5)]"></div>
                    Queued
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 font-medium">
                    <div class="h-0.5 w-3 rounded-full bg-[rgba(147,51,234,0.5)]"></div>
                    Processing
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 font-medium">
                    <div class="h-0.5 w-3 rounded-full bg-[#9333ea]"></div>
                    Processed
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 font-medium">
                    <div class="h-0.5 w-3 rounded-full bg-[#eab308]"></div>
                    Released
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 font-medium">
                    <div class="h-0.5 w-3 rounded-full bg-[#e11d48]"></div>
                    Failed
                </div>
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::card-body :expand="$expand" wire:poll.5s="">
        <div
            x-data="{
                loadingNewDataset: false,
                init() {
                    Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                    Livewire.hook('commit', ({ component, succeed }) => {
                        if (component.name === $wire.__instance.name) {
                            succeed(() => this.loadingNewDataset = false)
                        }
                    })
                }
            }"
            class="min-h-full flex flex-col"
            :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''"
        >
            @if (count($queues) === 0)
                <x-pulse::no-results class="flex-1" />
            @else
                <div class="grid gap-3 mx-px mb-px">
                    @foreach ($queues as $queue => $readings)
                        <div wire:key="{{ $queue }}">
                            <h3 class="font-bold text-gray-700 dark:text-gray-300">
                                @if ($showConnection)
                                    {{ $queue }}
                                @else
                                    {{ Str::after($queue, ':') }}
                                @endif
                            </h3>
                            @php $latest = $readings->last() @endphp
                            @php
                                $highest = $readings->map(fn ($reading) => max(
                                    $reading->queued,
                                    $reading->processing,
                                    $reading->released,
                                    $reading->processed,
                                    $reading->failed,
                                ))->max()
                            @endphp

                            <div class="mt-3 relative">
                                <div class="absolute -left-px -top-2 max-w-fit h-4 flex items-center px-1 text-xs leading-none text-white font-bold bg-purple-500 rounded after:[--triangle-size:4px] after:border-l-purple-500 after:absolute after:right-[calc(-1*var(--triangle-size))] after:top-[calc(50%-var(--triangle-size))] after:border-t-[length:var(--triangle-size)] after:border-b-[length:var(--triangle-size)] after:border-l-[length:var(--triangle-size)] after:border-transparent">
                                    @if ($config['sample_rate'] < 1)
                                        <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($highest) }}">~{{ number_format($highest * (1 / $config['sample_rate'])) }}</span>
                                    @else
                                        {{ number_format($highest) }}
                                    @endif
                                </div>

                                <div
                                    wire:ignore
                                    class="h-14"
                                    x-data="{
                                        init() {
                                            let chart = new Chart(
                                                this.$refs.canvas,
                                                {
                                                    type: 'line',
                                                    data: {
                                                        labels: @js(collect($readings)->pluck('date')),
                                                        datasets: [
                                                            {
                                                                label: 'Queued',
                                                                borderColor: 'rgba(107,114,128,0.5)',
                                                                borderWidth: 2,
                                                                borderCapStyle: 'round',
                                                                data: @js(collect($readings)->pluck('queued')->map(fn ($reading) => $reading * (1 / $config['sample_rate']))),
                                                                pointStyle: false,
                                                                tension: 0.2,
                                                                spanGaps: false,
                                                                order: 4,
                                                            },
                                                            {
                                                                label: 'Processing',
                                                                borderColor: 'rgba(147,51,234,0.5)',
                                                                borderWidth: 2,
                                                                borderCapStyle: 'round',
                                                                data: @js(collect($readings)->pluck('processing')->map(fn ($reading) => $reading * (1 / $config['sample_rate']))),
                                                                pointStyle: false,
                                                                tension: 0.2,
                                                                spanGaps: false,
                                                                order: 3,
                                                            },
                                                            {
                                                                label: 'Released',
                                                                borderColor: '#eab308',
                                                                borderWidth: 2,
                                                                borderCapStyle: 'round',
                                                                data: @js(collect($readings)->pluck('released')->map(fn ($reading) => $reading * (1 / $config['sample_rate']))),
                                                                pointStyle: false,
                                                                tension: 0.2,
                                                                spanGaps: false,
                                                                order: 2,
                                                            },
                                                            {
                                                                label: 'Processed',
                                                                borderColor: '#9333ea',
                                                                borderWidth: 2,
                                                                borderCapStyle: 'round',
                                                                data: @js(collect($readings)->pluck('processed')->map(fn ($reading) => $reading * (1 / $config['sample_rate']))),
                                                                pointStyle: false,
                                                                tension: 0.2,
                                                                spanGaps: false,
                                                                order: 1,
                                                            },
                                                            {
                                                                label: 'Failed',
                                                                borderColor: '#e11d48',
                                                                borderWidth: 2,
                                                                borderCapStyle: 'round',
                                                                data: @js(collect($readings)->pluck('failed')->map(fn ($reading) => $reading * (1 / $config['sample_rate']))),
                                                                pointStyle: false,
                                                                tension: 0.2,
                                                                spanGaps: false,
                                                                order: 0,
                                                            },
                                                        ],
                                                    },
                                                    options: {
                                                        maintainAspectRatio: false,
                                                        layout: {
                                                            autoPadding: false,
                                                            padding: {
                                                                top: 1,
                                                            },
                                                        },
                                                        scales: {
                                                            x: {
                                                                display: false,
                                                            },
                                                            y: {
                                                                display: false,
                                                                min: 1,
                                                                ticks: {
                                                                    stepSize: 1,
                                                                },
                                                            },
                                                        },
                                                        plugins: {
                                                            legend: {
                                                                display: false,
                                                            },
                                                            tooltip: {
                                                                callbacks: {
                                                                    beforeBody: (context) => context
                                                                        .map(item => `${item.dataset.label}: {{ $config['sample_rate'] < 1 ? '~' : ''}}${item.formattedValue}`)
                                                                        .join(', '),
                                                                    label: () => null,
                                                                },
                                                            },
                                                        },
                                                    },
                                                }
                                            )

                                            Livewire.on('queues-chart-update', ({ queues }) => {
                                                if (chart === undefined) {
                                                    return
                                                }

                                                if (queues['{{ $queue }}'] === undefined && chart) {
                                                    chart.destroy()
                                                    chart = undefined
                                                    return
                                                }

                                                chart.data.labels = queues['{{ $queue }}'].map(reading => reading.date)
                                                chart.data.datasets[0].data = queues['{{ $queue }}'].map(reading => reading.queued * (1 / {{ $config['sample_rate']}}))
                                                chart.data.datasets[1].data = queues['{{ $queue }}'].map(reading => reading.processing * (1 / {{ $config['sample_rate']}}))
                                                chart.data.datasets[2].data = queues['{{ $queue }}'].map(reading => reading.released * (1 / {{ $config['sample_rate']}}))
                                                chart.data.datasets[3].data = queues['{{ $queue }}'].map(reading => reading.processed * (1 / {{ $config['sample_rate']}}))
                                                chart.data.datasets[4].data = queues['{{ $queue }}'].map(reading => reading.failed * (1 / {{ $config['sample_rate']}}))
                                                chart.update()
                                            })
                                        }
                                    }"
                                >
                                    <canvas x-ref="canvas" class="ring-1 ring-gray-900/5 dark:ring-gray-100/10 bg-gray-50 dark:bg-gray-800 rounded-md shadow-sm"></canvas>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-pulse::card-body>
</x-pulse::card>
