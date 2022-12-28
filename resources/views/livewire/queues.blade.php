<x-pulse::card
    class="col-span-2"
    wire:poll=""
>
    <x-slot:title>
        <x-pulse::card-title class="flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-6 h-6 mr-1 stroke-gray-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
            </svg>
            Queues
        </x-pulse::card-title>
    </x-slot:title>

    <div class="max-h-56 h-full relative overflow-y-auto">
        TODO
    </div>
</x-pulse::card>
