<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="Queues">
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
        <div class="grid gap-4 mx-px mb-px">
            @foreach ($queues as $queue => $readings)
                <div>
                    <h3 class="font-bold text-gray-700 dark:text-gray-300">
                        {{ Str::after($queue, ':') }}
                        @if ($showConnection)
                            ({{ Str::before($queue, ':') }})
                        @endif
                    </h3>
                    @php $latest = $readings->last() @endphp
                    {{-- <p>Queued: {{ $latest->queued }} --}}
                    {{-- Failed: {{ $latest->failed }} --}}
                    {{-- Processed: {{ $latest->processed }}</p> --}}
                    <div
                        wire:ignore
                        class="h-12 mt-1"
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
                                                    top: 2,
                                                },
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
                                                    min: 1,
                                                    // max: 100,
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
                                                        label: (context) => `${context.label} - ${context.dataset.label}: ${context.formattedValue}`
                                                    },
                                                    displayColors: false,
                                                },
                                            },
                                        },
                                    }
                                )
                            }
                        }"
                    >
                        <canvas x-ref="canvas" class="w-full ring-1 ring-gray-900/10 dark:ring-gray-100/10 bg-white dark:bg-gray-800 rounded-md shadow-sm"></canvas>
                    </div>
                </div>
            @endforeach
        </div>
    </x-pulse::card-body>
</x-pulse::card>
