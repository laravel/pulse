@props(['expand' => false])
<div
    x-data="{
        init() {
            $nextTick(() => this.scroll())
        },
        scroll() {
            const { content, fade } = this.$refs

            if (! fade) {
                return
            }

            const distanceToBottom = content.scrollHeight - (content.scrollTop + content.clientHeight)

            if (distanceToBottom >= 24) {
                fade.style.transform = `scaleY(1)`
            } else {
                fade.style.transform = `scaleY(${distanceToBottom / 24})`
            }
        }
    }"
    {{ $attributes->merge(['class' => '@container/scroll-wrapper flex-grow flex w-full overflow-hidden' . ($expand ? '' : ' basis-56'), ':class' => "loading && 'opacity-25 animate-pulse'"]) }}
>
    <div x-ref="content" class="flex-grow basis-full overflow-y-auto scrollbar:w-1.5 scrollbar:h-1.5 scrollbar:bg-transparent scrollbar-track:bg-gray-100 scrollbar-thumb:rounded scrollbar-thumb:bg-gray-300 scrollbar-track:rounded dark:scrollbar-track:bg-gray-500/10 dark:scrollbar-thumb:bg-gray-500/50 supports-scrollbars" @scroll.debounce.5ms="scroll">
        {{ $slot }}
        <div x-ref="fade" class="h-6 origin-bottom fixed bottom-0 left-0 right-0 bg-gradient-to-t from-white dark:from-gray-900 pointer-events-none" wire:ignore></div>
    </div>
</div>
