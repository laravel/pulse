<?php

namespace Laravel\Pulse\Concerns;

use Illuminate\Database\Connection;

/**
 * @internal
 */
trait InteractsWithDatabaseConnection
{
    /**
     * Get the query's database connection.
     */
    protected function db(): Connection
    {
        return $this->db->connection(
            $this->config->get('pulse.storage.database.connection') ?? $this->config->get('database.default')
        );
    }
}
