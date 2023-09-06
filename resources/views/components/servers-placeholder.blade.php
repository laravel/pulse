@php
$cols = ! empty($cols) ? $cols : 'full';
$rows = ! empty($rows) ? $rows : 1;
@endphp
<section class="h-[52px] flex items-center justify-between default:col-span-full default:lg:col-span-{{ $cols }} default:row-span-{{ $rows }} {{ $class ?? '' }}">
    <div class="mt-4 h-8 w-1/12 bg-gray-100 dark:bg-gray-900 rounded animate-pulse"></div>
    <div class="mt-4 h-8 w-3/12 bg-gray-100 dark:bg-gray-900 rounded animate-pulse"></div>
    <div class="mt-4 h-8 w-3/12 bg-gray-100 dark:bg-gray-900 rounded animate-pulse"></div>
    <div class="mt-4 h-8 w-2/12 bg-gray-100 dark:bg-gray-900 rounded animate-pulse"></div>
</section>
