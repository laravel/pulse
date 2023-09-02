<x-pulse::card class="col-span-{{ $cols }}">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2 stroke-gray-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75c1.148 0 2.278.08 3.383.237 1.037.146 1.866.966 1.866 2.013 0 3.728-2.35 6.75-5.25 6.75S6.75 18.728 6.75 15c0-1.046.83-1.867 1.866-2.013A24.204 24.204 0 0112 12.75zm0 0c2.883 0 5.647.508 8.207 1.44a23.91 23.91 0 01-1.152 6.06M12 12.75c-2.883 0-5.647.508-8.208 1.44.125 2.104.52 4.136 1.153 6.06M12 12.75a2.25 2.25 0 002.248-2.354M12 12.75a2.25 2.25 0 01-2.248-2.354M12 8.25c.995 0 1.971-.08 2.922-.236.403-.066.74-.358.795-.762a3.778 3.778 0 00-.399-2.25M12 8.25c-.995 0-1.97-.08-2.922-.236-.402-.066-.74-.358-.795-.762a3.734 3.734 0 01.4-2.253M12 8.25a2.25 2.25 0 00-2.248 2.146M12 8.25a2.25 2.25 0 012.248 2.146M8.683 5a6.032 6.032 0 01-1.155-1.002c.07-.63.27-1.222.574-1.747m.581 2.749A3.75 3.75 0 0115.318 5m0 0c.427-.283.815-.62 1.155-.999a4.471 4.471 0 00-.575-1.752M4.921 6a24.048 24.048 0 00-.392 3.314c1.668.546 3.416.914 5.223 1.082M19.08 6c.205 1.08.337 2.187.392 3.314a23.882 23.882 0 01-5.223 1.082" />
            </svg>
            <span>
                <span title="Time: {{ $time }}; Run at: {{ $runAt }};">Exceptions</span>
                <small class="ml-2 text-gray-400 text-xs font-medium">past {{ $this->periodForHumans() }}</small>
            </span>
        </x-pulse::card-title>
        <div class="flex items-center gap-2">
            <div class="text-sm text-gray-700">Sort by</div>
            <select
                wire:model="orderBy"
                wire:change="$dispatch('exception-changed', { orderBy: $event.target.value })"
                class="rounded-md border-gray-200 text-gray-700 py-1 text-sm"
            >
                <option value="count">count</option>
                <option value="last_occurrence">recent</option>
            </select>
        </div>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <div x-data="{
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
        }">
            <div>
                <div :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    @if (count($exceptions) === 0)
                        <x-pulse::no-results />
                    @else
                        <x-pulse::table>
                            <x-pulse::thead>
                                <tr>
                                    <x-pulse::th class="w-full text-left">Type</x-pulse::th>
                                    <x-pulse::th class="text-center">Latest</x-pulse::th>
                                    <x-pulse::th class="text-right">Count</x-pulse::th>
                                </tr>
                            </x-pulse::thead>
                            <tbody>
                                @foreach ($exceptions as $exception)
                                    <tr>
                                        <x-pulse::td>
                                            <code class="block text-xs text-gray-900">
                                                {{ $exception->class }}
                                            </code>
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ $exception->location }}
                                            </p>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-center text-gray-700 text-sm font-bold whitespace-nowrap tabular-nums">
                                            {{ $exception->last_occurrence !== null ? Carbon\CarbonImmutable::parse($exception->last_occurrence)->ago(syntax: Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) : 'Unknown' }}
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm font-bold tabular-nums">
                                            {{ number_format($exception->count) }}
                                        </x-pulse::td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-pulse::table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-pulse::card>
