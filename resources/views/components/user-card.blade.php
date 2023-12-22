@props(['user' => null, 'name' => null, 'extra' => null, 'avatar' => null, 'stats' => null])

<div {{ $attributes->merge(['class' => 'flex items-center justify-between p-3 gap-3 bg-gray-50 dark:bg-gray-800/50 rounded']) }}>
    <div class="flex items-center gap-3 overflow-hidden">
        @if (isset($avatar))
            {{ $avatar }}
        @elseif ($user->avatar ?? false)
            <img src="{{ $user->avatar }}" alt="{{ $user->name }}" loading="lazy" class="rounded-full w-8 h-8 object-cover">
        @endif

        <div class="overflow-hidden">
            <div class="text-sm text-gray-900 dark:text-gray-100 font-medium truncate" title="{{ $user->name ?? $name }}">
                {{ $user->name ?? $name }}
            </div>

            <div class="text-xs text-gray-500 dark:text-gray-400 truncate" title="{{ $user->name ?? $extra }}">
                {{ $user->extra ?? $extra }}
            </div>
        </div>
    </div>

    @if (isset($stats))
        <div class="text-xl text-gray-900 dark:text-gray-100 font-bold tabular-nums">
            {{ $stats }}
        </div>
    @endif
</div>
