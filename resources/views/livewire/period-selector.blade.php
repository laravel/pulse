<select
    wire:model="period"
    wire:change="$emit('periodChanged', $event.target.value)"
    class="rounded-md border-gray-200 text-gray-700 py-1 text-sm"
>
    <option value="1-hour">1 hour</option>
    <option value="6-hours">6 hours</option>
    <option value="24-hours">24 hours</option>
    <option value="7-days">7 days</option>
</select>
