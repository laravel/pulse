<x-pulse::card class="col-span-{{ $cols }}">
    <x-slot:title>
        <x-pulse::card-title class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-2 stroke-gray-500">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
            </svg>
            <span>
                <span title="Time: {{ $time }}ms; Run at: {{ $runAt }}">Slow Jobs</span>
                <small class="ml-2 text-gray-400 text-xs font-medium">{{ config('pulse.slow_job_threshold') }}ms threshold, past {{ $this->periodForHumans() }}</small>
            </span>
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto" wire:poll.5s>
        <div x-data="{
            loadingNewDataset: false,
            init() {
                Livewire.on('period-changed', () => (this.loadingNewDataset = true))

                Livewire.hook('commit', ({ component, succeed }) => {
                    if (component.name === 'slow-jobs') {
                        succeed(() => this.loadingNewDataset = false)
                    }
                })
            }
        }">
            <div>
                <div :class="loadingNewDataset ? 'opacity-25 animate-pulse' : ''">
                    @if (count($slowJobs) === 0)
                        <x-pulse::no-results />
                    @else
                        <x-pulse::table class="table-fixed">
                            <x-pulse::thead>
                                <tr>
                                    <x-pulse::th class="text-left">Job</x-pulse::th>
                                    <x-pulse::th class="text-right w-24">Count</x-pulse::th>
                                    <x-pulse::th class="text-right w-24">Slowest</x-pulse::th>
                                </tr>
                            </x-pulse::thead>
                            <tbody>
                                @foreach ($slowJobs as $job)
                                    <tr wire:key="{{ $job->job }}">
                                        <x-pulse::td>
                                            <code class="block text-xs text-gray-900 truncate" title="{{ $job->job }}">
                                                {{ $job->job }}
                                            </code>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm w-24 tabular-nums">
                                            <strong>{{ number_format($job->count) }}</strong>
                                        </x-pulse::td>
                                        <x-pulse::td class="text-right text-gray-700 text-sm w-24 whitespace-nowrap tabular-nums">
                                            @if ($job->slowest === null)
                                                <strong>Unknown</strong>
                                            @else
                                                <strong>{{ number_format($job->slowest) ?: '<1' }}</strong> ms
                                            @endif
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
