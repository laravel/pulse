<select
    wire:model="period"
    wire:change="$emit('periodChanged', $event.target.value)"
    class="rounded-md border-gray-200 text-gray-700 py-1 text-sm"
>
    <option value="1_hour">1 hour</option>
    <option value="6_hours">6 hours</option>
    <option value="24_hours">24 hours</option>
    <option value="7_days">7 days</option>
</select>
