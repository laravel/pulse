<x-pulse::card class="animate-pulse {{ $class ?? '' }}">
    <x-slot:title>
        <div class="rounded bg-gray-100 h-6 w-1/2"></div>
    </x-slot:title>
    <div class="space-y-4">
        <div class="rounded bg-gray-100 h-4 w-1/3"></div>
        <div class="rounded bg-gray-100 h-4 w-2/3"></div>
        <div class="rounded bg-gray-100 h-4 w-3/5"></div>
    </div>
</x-pulse::card>
