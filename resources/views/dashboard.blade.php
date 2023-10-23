{{--
|--------------------------------------------------------------------------
| Pulse Dashboard
|--------------------------------------------------------------------------
|
| Here you may configure the cards and layout of the Pulse dashboard.
|
| There are 12 columns available by default, but you may customize this by specifying the `cols` attribute on the `<x-pulse>` component as any number between between 1 and 12.
| You may also make the dashboard full width by setting the `fullWidth` attribute of the `x-pulse` to `true`.
|
| Most cards span 6 columns and 1 row on larger screens by default.
| You may customize this by specifying the `cols` attribute as a number between 1 and 12, and the `rows` attribute between 1 and 6.
|
| Most cards also allow setting an `expand` attribute to `true` to show all items, instead of scrolling.
|
--}}

<x-pulse :full-width="false" cols="12">

    {{-- <livewire:pulse.servers cols="full" /> --}}

    {{-- <livewire:pulse.usage cols="4" rows="2" /> --}}
    {{-- <livewire:pulse.usage type="dispatched_job_counts" /> --}}
    {{-- <livewire:pulse.usage type="slow_endpoint_counts" /> --}}
    {{-- <livewire:pulse.usage type="request_counts" /> --}}

    {{-- <livewire:pulse.queues cols="4" /> --}}

    {{-- <livewire:pulse.cache cols="4" /> --}}

    {{-- <livewire:pulse.slow-queries cols="8" /> --}}

    {{-- <livewire:pulse.exceptions cols="6" /> --}}

    {{-- <livewire:pulse.slow-routes cols="6" /> --}}

    <livewire:pulse.slow-jobs cols="6" />

    <livewire:pulse.slow-outgoing-requests cols="6" />

</x-pulse>
