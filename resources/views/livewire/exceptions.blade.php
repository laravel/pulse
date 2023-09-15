<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Exceptions"
        title="Time: {{ $time }}; Run at: {{ $runAt }};"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.bug-ant />
        </x-slot:icon>
        <x-slot:actions>
            <div class="flex items-center bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md pl-3 focus-within:ring">
                <div class="text-xs sm:text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap py-1">Sort by</div>
                &nbsp;
                <select
                    wire:model="orderBy"
                    wire:change="$dispatch('exception-changed', { orderBy: $event.target.value })"
                    class="rounded-md overflow-ellipsis w-full border-0 bg-transparent pl-0 pr-8 py-1 text-gray-700 dark:text-gray-300 text-xs sm:text-sm shadow-none border-transparent focus:ring-0"
                >
                    <option value="count">count</option>
                    <option value="last_occurrence">recent</option>
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
                        if (component.name === 'exceptions') {
                            succeed(() => this.loadingNewDataset = false)
                        }
                    })
                }
            }"
            :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''"
        >
            @if (count($exceptions) === 0)
                <x-pulse::no-results />
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
                        @foreach ($exceptions as $exception)
                            <tr class="h-2 first:h-0"></tr>
                            <tr wire:key="{{ $exception->class.$exception->location }}">
                                <x-pulse::td class="break-words overflow-hidden">
                                    <code class="block text-xs text-gray-900 dark:text-gray-100 overflow-ellipsis">
                                        {{ $exception->class }}
                                    </code>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 overflow-ellipsis">
                                        {{ $exception->location }}
                                    </p>
                                </x-pulse::td>
                                <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm font-bold whitespace-nowrap tabular-nums">
                                    {{ $exception->last_occurrence !== null ? Carbon\CarbonImmutable::parse($exception->last_occurrence)->ago(syntax: Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) : 'Unknown' }}
                                </x-pulse::td>
                                <x-pulse::td class="text-right text-gray-700 dark:text-gray-300 text-sm font-bold tabular-nums">
                                    {{ number_format($exception->count) }}
                                </x-pulse::td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-pulse::table>
            @endif
        </div>
    </x-pulse::card-body>
</x-pulse::card>
