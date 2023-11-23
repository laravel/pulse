<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Exceptions"
        title="Time: {{ number_format($time) }}ms; Run at: {{ $runAt }};"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.bug-ant />
        </x-slot:icon>
        <x-slot:actions>
            <div class="flex border border-gray-200 dark:border-gray-700 overflow-hidden rounded-md focus-within:ring">
                <label class="px-3 flex items-center border-r border-gray-200 dark:border-gray-700 text-xs sm:text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap bg-gray-100 dark:bg-gray-800/50">Sort by</label>
                <select
                    wire:model.live="orderBy"
                    wire:change="$dispatch('exception-changed', { orderBy: $event.target.value })"
                    class="overflow-ellipsis w-full border-0 pl-3 pr-8 py-1 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs sm:text-sm shadow-none focus:ring-0"
                >
                    <option value="count">count</option>
                    <option value="latest">latest</option>
                </select>
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::card-body :expand="$expand" wire:poll.5s="">
        <div
            x-data="{
                loadingNewDataset: false,
                init() {
                    Livewire.on('period-changed', () => (this.loadingNewDataset = true))
                    Livewire.on('exception-changed', () => (this.loadingNewDataset = true))

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
            @if (count($exceptions) === 0)
                <x-pulse::no-results class="flex-1" />
            @else
                <x-pulse::table>
                    <colgroup>
                        <col width="100%" />
                        <col width="0%" />
                        <col width="0%" />
                    </colgroup>
                    <x-pulse::thead>
                        <tr>
                            <x-pulse::th class="text-left">Type</x-pulse::th>
                            <x-pulse::th class="text-right">Latest</x-pulse::th>
                            <x-pulse::th class="text-right">Count</x-pulse::th>
                        </tr>
                    </x-pulse::thead>
                    <tbody>
                        @foreach ($exceptions->take(100) as $exception)
                            <tr class="h-2 first:h-0"></tr>
                            <tr wire:key="{{ $exception->class.$exception->location }}">
                                <x-pulse::td class="break-words overflow-hidden">
                                    <code class="block text-xs text-gray-900 dark:text-gray-100 truncate">
                                        {{ $exception->class }}
                                    </code>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                                        {{ $exception->location }}
                                    </p>
                                </x-pulse::td>
                                <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm font-bold whitespace-nowrap tabular-nums">
                                    {{ $exception->latest !== null ? Carbon\CarbonImmutable::parse($exception->latest)->ago(syntax: Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) : 'Unknown' }}
                                </x-pulse::td>
                                <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm font-bold tabular-nums">
                                    @if ($config['sample_rate'] < 1)
                                        <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($exception->count) }}">~{{ number_format($exception->count * (1 / $config['sample_rate'])) }}</span>
                                    @else
                                        {{ number_format($exception->count) }}
                                    @endif
                                </x-pulse::td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-pulse::table>
            @endif

            @if ($exceptions->count() > 100)
                <div class="mt-2 text-xs text-gray-400 text-center">Limited to 100 entries</div>
            @endif
        </div>
    </x-pulse::card-body>
</x-pulse::card>
