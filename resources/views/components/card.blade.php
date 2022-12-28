<div {{ $attributes->merge(['class' => 'flex flex-col p-5 bg-white rounded-md shadow-lg']) }}>
    <div class="flex items-center justify-between">
        {{ $title }}
    </div>
    <div class="flex-1 mt-4">
        {{ $slot }}
    </div>
</div>
