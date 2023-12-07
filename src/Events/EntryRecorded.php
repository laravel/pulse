<?php

namespace Laravel\Pulse\Events;

use Laravel\Pulse\Entry;

class EntryRecorded
{
    public function __construct(public Entry $entry)
    {
        //
    }
}
