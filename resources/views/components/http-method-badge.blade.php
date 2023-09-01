@props(['method'])

@php
$colorClasses = match ($method) {
    'GET', 'OPTIONS' => 'text-purple-600 bg-purple-200 border-purple-300',
    'POST', 'PUT', 'PATCH' => 'text-blue-600 bg-blue-200 border-blue-300',
    'DELETE' => 'text-red-600 bg-red-200 border-red-300',
    default => 'text-gray-600 bg-gray-200 border-gray-300',
}
@endphp

<span {{ $attributes->merge(['class' => "text-xs font-mono px-1 border rounded font-semibold $colorClasses"]) }}>{{ $method }}</span>
