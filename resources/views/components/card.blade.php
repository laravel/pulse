@props(['cols' => 6, 'rows' => 1])
<div {{ $attributes->merge(['class' => "@container flex flex-col p-3 sm:p-6 bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-900/5 default:col-span-full default:lg:col-span-{$cols} default:row-span-{$rows}"]) }}>
    {{ $slot }}
</div>
