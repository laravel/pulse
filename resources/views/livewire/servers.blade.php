<div wire:poll>
    @foreach ($this->servers as $server)
        <div>
            {{ $server['name'] }}
            @php
                $lastReading = collect($server['readings'])->last();
            @endphp
            {{ $lastReading['cpu'] }}%
            {{ $lastReading['memory_used'] }} / {{ $lastReading['memory_total'] }}
            @foreach ($lastReading['storage'] as $storage)
                {{ $storage->directory }}
                {{ $storage->used }} / {{ $storage->total }}
            @endforeach
        </div>
    @endforeach
</div>
