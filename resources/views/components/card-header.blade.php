@props(['name', 'title' => '', 'details' => null])
<div class="flex flex-wrap justify-between gap-3 mb-3 @md:mb-6">
    <div class="flex flex-1 basis-0 flex-grow-[10000] items-start gap-2">
        <div class="[&>svg]:flex-shrink-0 [&>svg]:w-6 [&>svg]:h-6 [&>svg]:stroke-gray-500 [&>svg]:dark:stroke-gray-600">
            {{ $icon }}
        </div>
        <div class="flex flex-wrap items-baseline gap-x-2">
            <h2 class="text-base font-bold text-gray-600 dark:text-gray-300 whitespace-nowrap" title="{{ $title }}">{{ $name }}</h2>
            @if ($details)
                <p class="text-gray-400 dark:text-gray-600 font-medium whitespace-nowrap"><small class="text-xs">{{ $details }}</small></p>
            @endif
        </div>
    </div>
    @if ($actions ?? false)
        <div class="flex flex-grow">
            <div class="w-full">
                {{ $actions }}
            </div>
        </div>
    @endif
</div>
