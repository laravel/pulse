<div class="flex gap-2">
    <button wire:click="setPeriod('1_hour')" class="font-semibold text-lg {{ $period === '1_hour' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600 hover:text-gray-400 dark:hover:text-gray-500'}}">1h</button>
    <button wire:click="setPeriod('6_hours')" class="font-semibold text-lg {{ $period === '6_hours' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600 hover:text-gray-400 dark:hover:text-gray-500'}}">6h</button>
    <button wire:click="setPeriod('24_hours')" class="font-semibold text-lg {{ $period === '24_hours' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600 hover:text-gray-400 dark:hover:text-gray-500'}}">24h</button>
    <button wire:click="setPeriod('7_days')" class="font-semibold text-lg {{ $period === '7_days' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600 hover:text-gray-400 dark:hover:text-gray-500'}}">7d</button>
</div>
