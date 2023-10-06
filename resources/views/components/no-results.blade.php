@props(['happy' => false])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center p-4']) }}>
    @if ($happy)
        <x-pulse::icons.sparkles class="h-6 w-6 stroke-gray-300" />
    @else
        <x-pulse::icons.clipboard class="h-6 w-6 stroke-gray-300" />
    @endif
    <p class="mt-2 text-sm text-gray-400">
        No results
    </p>
</div>
