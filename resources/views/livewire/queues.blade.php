@php
    use Illuminate\Support\Str;
@endphp
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="{{ __('Queues') }}"
        title="{{ __('Time: :timems', ['time' => number_format($time)]) }}; {{ __('Run at:') }} {{ $runAt }};"
        details="{{ __('past :period', ['period' => $this->periodForHumans()]) }}"
    >
        <x-slot:icon>
            <x-pulse::icons.queue-list />
        </x-slot:icon>
        <x-slot:actions>
            <div class="flex flex-wrap gap-4">
                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                    <div class="h-0.5 w-3 rounded-full bg-[rgba(107,114,128,0.5)]"></div>
                    {{ __('Queued') }}
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                    <div class="h-0.5 w-3 rounded-full bg-[rgba(147,51,234,0.5)]"></div>
                    {{ __('Processing') }}
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                    <div class="h-0.5 w-3 rounded-full bg-[#9333ea]"></div>
                    {{ __('Processed') }}
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                    <div class="h-0.5 w-3 rounded-full bg-[#eab308]"></div>
                    {{ __('Released') }}
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                    <div class="h-0.5 w-3 rounded-full bg-[#e11d48]"></div>
                    {{ __('Failed') }}
                </div>
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($queues->isEmpty())
            <x-pulse::no-results />
        @else
            <div class="grid gap-3 mx-px mb-px">
                @foreach ($queues as $queue => $readings)
                    <div wire:key="{{ $queue.$this->period }}">
                        <h3 class="font-bold text-gray-700 dark:text-gray-300">
                            @if ($showConnection)
                                {{ $queue }}
                            @else
                                {{ Str::after($queue, ':') }}
                            @endif
                        </h3>
                        @php
                            $highest = $readings->flatten()->max();
                        @endphp

                        <div class="mt-3 relative">
                            <div class="absolute -left-px -top-2 max-w-fit h-4 flex items-center px-1 text-xs leading-none text-white font-bold bg-purple-500 rounded after:[--triangle-size:4px] after:border-l-purple-500 after:absolute after:right-[calc(-1*var(--triangle-size))] after:top-[calc(50%-var(--triangle-size))] after:border-t-[length:var(--triangle-size)] after:border-b-[length:var(--triangle-size)] after:border-l-[length:var(--triangle-size)] after:border-transparent">
                                @if ($config['sample_rate'] < 1)
                                    <span title="{{ __('Sample rate:') }} {{ $config['sample_rate'] }}, {{ __('Raw value:') }} {{ number_format($highest) }}">~{{ number_format($highest * (1 / $config['sample_rate'])) }}</span>
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
                                                    labels: @js($readings->first()->keys()),
                                                    datasets: [
                                                        {
                                                            label: '{{ __('Queued') }}',
                                                            borderColor: 'rgba(107,114,128,0.5)',
                                                            data: @js($readings->get('queued')->values()->map(fn ($value) => $value * (1 / $config['sample_rate']))),
                                                            order: 4,
                                                        },
                                                        {
                                                            label: '{{ __('Processing') }}',
                                                            borderColor: 'rgba(147,51,234,0.5)',
                                                            data: @js($readings->get('processing')->values()->map(fn ($value) => $value * (1 / $config['sample_rate']))),
                                                            order: 3,
                                                        },
                                                        {
                                                            label: '{{ __('Released') }}',
                                                            borderColor: '#eab308',
                                                            data: @js($readings->get('released')->values()->map(fn ($value) => $value * (1 / $config['sample_rate']))),
                                                            order: 2,
                                                        },
                                                        {
                                                            label: '{{ __('Processed') }}',
                                                            borderColor: '#9333ea',
                                                            data: @js($readings->get('processed')->values()->map(fn ($value) => $value * (1 / $config['sample_rate']))),
                                                            order: 1,
                                                        },
                                                        {
                                                            label: '{{ __('Failed') }}',
                                                            borderColor: '#e11d48',
                                                            data: @js($readings->get('failed')->values()->map(fn ($value) => $value * (1 / $config['sample_rate']))),
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
                                                    datasets: {
                                                        line: {
                                                            borderWidth: 2,
                                                            borderCapStyle: 'round',
                                                            pointHitRadius: 10,
                                                            pointStyle: false,
                                                            tension: 0.2,
                                                            spanGaps: false,
                                                            segment: {
                                                                borderColor: (ctx) => ctx.p0.raw === 0 && ctx.p1.raw === 0 ? 'transparent' : undefined,
                                                            }
                                                        }
                                                    },
                                                    scales: {
                                                        x: {
                                                            display: false,
                                                        },
                                                        y: {
                                                            display: false,
                                                            min: 0,
                                                            max: @js($highest),
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

                                            chart.data.labels = Object.keys(Object.values(queues['{{ $queue }}'])[0])
                                            chart.options.scales.y.max = Math.max(...Object.values(queues['{{ $queue }}']).map(readings => Math.max(...Object.values(readings))))
                                            chart.data.datasets[0].data = Object.values(queues['{{ $queue }}']['queued']).map(value => value * (1 / {{ $config['sample_rate']}}))
                                            chart.data.datasets[1].data = Object.values(queues['{{ $queue }}']['processing']).map(value => value * (1 / {{ $config['sample_rate']}}))
                                            chart.data.datasets[2].data = Object.values(queues['{{ $queue }}']['released']).map(value => value * (1 / {{ $config['sample_rate']}}))
                                            chart.data.datasets[3].data = Object.values(queues['{{ $queue }}']['processed']).map(value => value * (1 / {{ $config['sample_rate']}}))
                                            chart.data.datasets[4].data = Object.values(queues['{{ $queue }}']['failed']).map(value => value * (1 / {{ $config['sample_rate']}}))
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
    </x-pulse::scroll>
</x-pulse::card>
