<?php

namespace Laravel\Pulse\Contracts;

interface Update
{
    public function perform(): void;
}
