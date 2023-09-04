<x-pulse::card :cols="$cols ?? null" :rows="$rows ?? null" :class="$class ?? ''">
    <div class="h-[30px] flex items-center w-full mb-3 @md:mb-6">
        <div class="rounded bg-gray-50 dark:bg-gray-800 h-6 w-1/2 animate-pulse"></div>
    </div>
    <div class="space-y-4 h-56">
        <div class="rounded bg-gray-50 dark:bg-gray-800 h-12 animate-pulse"></div>
        <div class="rounded bg-gray-50 dark:bg-gray-800 h-12 animate-pulse"></div>
        <div class="rounded bg-gray-50 dark:bg-gray-800 h-12 animate-pulse"></div>
    </div>
</x-pulse::card>
