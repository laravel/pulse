<div {{ $attributes->merge(['class' => 'flex flex-col p-6 bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5']) }}>
    <div class="flex items-center justify-between">
        {{ $title }}
    </div>
    <div class="flex-1 mt-6">
        {{ $slot }}
    </div>
</div>
