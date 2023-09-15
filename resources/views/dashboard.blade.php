<x-pulse>
    <livewire:pulse.servers />
    <livewire:pulse.usage />
    {{-- <livewire:pulse.usage type="dispatched_job_counts" /> --}}
    {{-- <livewire:pulse.usage type="slow_endpoint_counts" /> --}}
    {{-- <livewire:pulse.usage type="request_counts" /> --}}
    <livewire:pulse.exceptions />
    <livewire:pulse.slow-routes />
    <livewire:pulse.slow-queries />
    <livewire:pulse.slow-jobs />
    <livewire:pulse.slow-outgoing-requests />
    <livewire:pulse.cache />
    <livewire:pulse.queues />
</x-pulse>
