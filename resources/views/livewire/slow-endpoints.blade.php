<x-pulse::card
    class="col-span-3"
    wire:poll=""
>
    <x-slot:title>
        <x-pulse::card-title class="flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-1 stroke-gray-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Slow Endpoints
            <small class="ml-2 text-gray-400 text-xs font-medium">&gt;&equals;{{ config('pulse.slow_endpoint_threshold') }}ms, past 7 days</small>
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto">
        TODO
    </div>
</x-pulse::card>
