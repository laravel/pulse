<?php

namespace Laravel\Pulse\Support;

use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

class DatabaseConnectionResolver
{
    /**
     * Create a new database connection resolver instance.
     */
    public function __construct(
        protected DatabaseManager $db,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Get the query's database connection.
     */
    public function connection(): Connection
    {
        return $this->db->connection(
            $this->config->get('pulse.storage.database.connection') ?? $this->config->get('database.default')
        );
    }
}
