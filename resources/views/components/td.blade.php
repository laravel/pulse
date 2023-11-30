@props(['numeric' => false])
<td {{ $attributes->merge(['class' => 'first:rounded-l-md last:rounded-r-md text-sm bg-gray-50 dark:bg-gray-800/50 first:pl-3 last:pr-3 px-1 @sm:px-3 py-3' . ($numeric ? ' text-right tabular-nums whitespace-nowrap' : '')]) }}>
    {{ $slot }}
</td>
