<div class="flex gap-2">
    <button wire:click="setPeriod('1_hour')" class="font-semibold text-lg {{ $period === '1_hour' ? 'text-gray-700' : 'text-gray-300 hover:text-gray-400'}}">1h</button>
    <button wire:click="setPeriod('6_hours')" class="font-semibold text-lg {{ $period === '6_hours' ? 'text-gray-700' : 'text-gray-300 hover:text-gray-400'}}">6h</button>
    <button wire:click="setPeriod('24_hours')" class="font-semibold text-lg {{ $period === '24_hours' ? 'text-gray-700' : 'text-gray-300 hover:text-gray-400'}}">24h</button>
    <button wire:click="setPeriod('7_days')" class="font-semibold text-lg {{ $period === '7_days' ? 'text-gray-700' : 'text-gray-300 hover:text-gray-400'}}">7d</button>
</div>
