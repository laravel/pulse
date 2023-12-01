<div class="flex"
    x-data="{
        setPeriod(period) {
            let query = new URLSearchParams(window.location.search)
            if (period === '1_hour') {
                query.delete('period')
            } else {
                query.set('period', period)
            }

            window.location = `${location.pathname}?${query}`
        }
    }"
>
    <button @click="setPeriod('1_hour')" class="p-1 font-semibold sm:text-lg {{ $period === '1_hour' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600 hover:text-gray-400 dark:hover:text-gray-500'}}">1h</button>
    <button @click="setPeriod('6_hours')" class="p-1 font-semibold sm:text-lg {{ $period === '6_hours' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600 hover:text-gray-400 dark:hover:text-gray-500'}}">6h</button>
    <button @click="setPeriod('24_hours')" class="p-1 font-semibold sm:text-lg {{ $period === '24_hours' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600 hover:text-gray-400 dark:hover:text-gray-500'}}">24h</button>
    <button @click="setPeriod('7_days')" class="p-1 font-semibold sm:text-lg {{ $period === '7_days' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600 hover:text-gray-400 dark:hover:text-gray-500'}}">7d</button>
</div>
