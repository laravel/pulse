@props(['label', 'options'])
<div {{ $attributes->only('class')->merge(['class' => 'flex border border-gray-200 dark:border-gray-700 overflow-hidden rounded-md focus-within:ring']) }}>
    <label class="px-3 flex items-center border-r border-gray-200 dark:border-gray-700 text-xs sm:text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap bg-gray-100 dark:bg-gray-800/50">{{ $label }}</label>
    <select
        {{ $attributes->except('class') }}
        class="overflow-ellipsis w-full border-0 pl-3 pr-8 py-1 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs sm:text-sm shadow-none focus:ring-0"
    >
        @foreach ($options as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
</div>
