<?php

namespace Laravel\Pulse\Queries\Concerns;

use Illuminate\Database\Connection;

trait InteractsWithConnection
{
    /**
     * Get the queries connection.
     */
    protected function connection(): Connection
    {
        return $this->db->connection($this->config->get(
            'pulse.storage.database.connection'
        ));
    }
}
