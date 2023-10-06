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
            <div class="flex gap-4">
                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 font-medium">
                    <div class="h-0.5 w-5 rounded-full bg-[rgba(147,51,234,0.5)]"></div>
                    Queued
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 font-medium">
                    <div class="h-0.5 w-5 rounded-full bg-[#9333ea]"></div>
                    Processed
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 font-medium">
                    <div class="h-0.5 w-5 rounded-full bg-[#e11d48]"></div>
                    Failed
                </div>
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::card-body :expand="$expand" wire:poll.5s="">
        <div class="grid gap-3 mx-px mb-px">
            @foreach ($queues as $queue => $readings)
                <div>
                    <h3 class="font-bold text-gray-700 dark:text-gray-300">
                        {{ Str::after($queue, ':') }}
                        @if ($showConnection)
                            ({{ Str::before($queue, ':') }})
                        @endif
                    </h3>
                    @php $latest = $readings->last() @endphp
                    @php
                        $highest = $readings->map(fn ($reading) => max(
                            $reading->queued,
                            $reading->processed,
                            $reading->failed,
                        ))->max()
                    @endphp

                    <div class="mt-3 relative">
                        <div class="absolute -left-px -top-2 max-w-fit h-4 flex items-center px-1 text-xs leading-none text-white font-bold bg-purple-500 rounded after:[--triangle-size:4px] after:border-l-purple-500 after:absolute after:right-[calc(-1*var(--triangle-size))] after:top-[calc(50%-var(--triangle-size))] after:border-t-[length:var(--triangle-size)] after:border-b-[length:var(--triangle-size)] after:border-l-[length:var(--triangle-size)] after:border-transparent">{{ number_format($highest) }}</div>

                        <div
                            wire:ignore
                            class="h-12"
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
                                                        borderColor: 'rgba(147,51,234,0.5)',
                                                        borderWidth: 2,
                                                        borderCapStyle: 'round',
                                                        data: @js(collect($readings)->pluck('queued')),
                                                        pointStyle: false,
                                                        tension: 0.2,
                                                        spanGaps: false,
                                                    },
                                                    {
                                                        label: 'Processed',
                                                        borderColor: '#9333ea',
                                                        borderWidth: 2,
                                                        borderCapStyle: 'round',
                                                        data: @js(collect($readings)->pluck('processed')),
                                                        pointStyle: false,
                                                        tension: 0.2,
                                                        spanGaps: false,
                                                    },
                                                    {
                                                        label: 'Failed',
                                                        borderColor: '#e11d48',
                                                        borderWidth: 2,
                                                        borderCapStyle: 'round',
                                                        data: @js(collect($readings)->pluck('failed')),
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
                                                            title: () => '',
                                                            label: (context) => `${context.label} - ${context.dataset.label}: ${context.formattedValue}`
                                                        },
                                                        displayColors: false,
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
                                        chart.data.datasets[0].data = queues['{{ $queue }}'].map(reading => reading.queued)
                                        chart.data.datasets[1].data = queues['{{ $queue }}'].map(reading => reading.processed)
                                        chart.data.datasets[2].data = queues['{{ $queue }}'].map(reading => reading.failed)
                                        chart.update()
                                    })
                                }
                            }"
                        >
                            <canvas x-ref="canvas" class="ring-1 ring-gray-900/10 dark:ring-gray-100/10 bg-white dark:bg-gray-800 rounded-md shadow-sm"></canvas>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-pulse::card-body>
</x-pulse::card>
