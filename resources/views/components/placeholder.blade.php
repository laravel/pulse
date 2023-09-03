<x-pulse::card class="col-span-{{ $cols }}">
    <x-slot:title>
        <div class="h-[30px] flex items-center w-full">
            <div class="rounded bg-gray-50 dark:bg-gray-800 h-6 w-1/2 animate-pulse"></div>
        </div>
    </x-slot:title>
    <div class="space-y-4 h-56">
        <div class="rounded bg-gray-50 dark:bg-gray-800 h-12 animate-pulse"></div>
        <div class="rounded bg-gray-50 dark:bg-gray-800 h-12 animate-pulse"></div>
        <div class="rounded bg-gray-50 dark:bg-gray-800 h-12 animate-pulse"></div>
    </div>
</x-pulse::card>
