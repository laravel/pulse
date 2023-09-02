<x-pulse::card class="animate-pulse col-span-{{ $cols }}">
    <x-slot:title>
        <div class="h-[30px] flex items-center w-full">
            <div class="rounded bg-gray-50 h-6 w-1/2"></div>
        </div>
    </x-slot:title>
    <div class="space-y-4 h-56">
        <div class="rounded bg-gray-50 h-12"></div>
        <div class="rounded bg-gray-50 h-12"></div>
        <div class="rounded bg-gray-50 h-12"></div>
    </div>
</x-pulse::card>
