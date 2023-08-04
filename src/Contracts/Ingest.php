<?php

namespace Laravel\Pulse\Contracts;

interface Ingest
{
    public function ingestSilently(array $entries, array $updates): void;
}
