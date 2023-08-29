@props(['method'])

@php
$colorClasses = match ($method) {
    'GET' => 'text-purple-600 bg-purple-200',
    'OPTIONS' => 'text-purple-600 bg-purple-200',
    'POST' => 'text-blue-600 bg-blue-200',
    'PATCH' => 'text-blue-600 bg-blue-200',
    'PUT' => 'text-blue-600 bg-blue-200',
    'DELETE' => 'text-red-600 bg-red-200',
    default => 'text-gray-600 bg-gray-200',
}
@endphp

<span {{ $attributes->merge(['class' => "text-xs font-mono px-1 rounded font-semibold $colorClasses"]) }}>{{ $method }}</span>
