<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Validation Errors"
        title="Time: {{ number_format($time) }}ms; Run at: {{ $runAt }};"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
</svg>
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($validationErrors->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <colgroup>
                    <col width="0%" />
                    <col width="100%" />
                    <col width="0%" />
                    <col width="0%" />
                </colgroup>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Input</x-pulse::th>
                        <x-pulse::th>Via</x-pulse::th>
                        <x-pulse::th class="text-right">Count</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($validationErrors->take(100) as $validationError)
                        <tr class="h-2 first:h-0"></tr>
                        <tr wire:key="{{ $validationError->method.$validationError->uri.$validationError->name.$this->period }}">
                            <x-pulse::td>
                                {{ $validationError->name }}
                            </x-pulse::td>
                            <x-pulse::td>
                                <div class="flex flex-col">
                                    <div class="mt-2">
                                        <div class="flex gap-2">
                                            <x-pulse::http-method-badge :method="$validationError->method" />
                                            <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $validationError->uri }}">
                                                {{ $validationError->uri }}
                                            </code>
                                        </div>
                                    </div>
                                    @if ($validationError->action)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate" table="{{ $validationError->action }}">
                                            {{ $validationError->action }}
                                        </p>
                                    @endif
                                </div>
                            </x-pulse>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                @if ($config['sample_rate'] < 1)
                                    <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($validationError->count) }}">~{{ number_format($validationError->count * (1 / $config['sample_rate'])) }}</span>
                                @else
                                    {{ number_format($validationError->count) }}
                                @endif
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>

            @if ($validationErrors->count() > 100)
                <div class="mt-2 text-xs text-gray-400 text-center">Limited to 100 entries</div>
            @endif
        @endif
    </x-pulse::scroll>
</x-pulse::card>

