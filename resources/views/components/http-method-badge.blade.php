@props(['method'])

@php
$colorClasses = match ($method) {
    'GET', 'OPTIONS' => 'text-purple-400 bg-purple-50 border-purple-200',
    'POST', 'PUT', 'PATCH' => 'text-blue-400 bg-blue-50 border-blue-200',
    'DELETE' => 'text-red-400 bg-red-50 border-red-200',
    default => 'text-gray-400 bg-gray-50 border-gray-200',
}
@endphp

<span {{ $attributes->merge(['class' => "text-xs font-mono px-1 border rounded font-semibold $colorClasses"]) }}>{{ $method }}</span>
