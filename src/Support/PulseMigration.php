<?php

namespace Laravel\Pulse\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PulseMigration extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return Config::get('pulse.storage.database.connection');
    }

    /**
     * Determine if the migration should run.
     */
    protected function shouldRun(): bool
    {
        if (in_array($this->driver(), ['mysql', 'pgsql'])) {
            return true;
        }

        if (! App::environment('testing')) {
            throw new RuntimeException("Pulse does not support the [{$this->driver()}] database driver.");
        }

        if (Config::get('pulse.enabled')) {
            throw new RuntimeException("Pulse does not support the [{$this->driver()}] database driver. You can disable Pulse in your testsuite by setting `<env name=\"PULSE_ENABLED\" value=\"false\"/>` in your project's `phpunit.xml` file.");
        }

        return true;
    }

    /**
     * The database connection driver.
     */
    protected function driver(): string
    {
        return DB::connection($this->getConnection())->getDriverName();
    }
}
