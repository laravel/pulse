<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Ingests\Database;
use Laravel\Pulse\Ingests\Redis;

class WorkCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:work';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Process the data from the stream.';

    /**
     * Handle the command.
     */
    public function handle(Redis $redisIngest, Database $databaseIngest): void
    {
        $lastTrimmedDatabaseAt = (new CarbonImmutable)->startOfMinute();

        while (true) {
            $now = new CarbonImmutable;

            if ($now->subMinute()->greaterThan($lastTrimmedDatabaseAt)) {
                $this->comment('Trimming the database at '.$now->toDateTimeString());

                $databaseIngest->trim($now->subWeek());

                $lastTrimmedDatabaseAt = $now;
            }

            $processed = $redisIngest->processEntries(1000);

            if ($processed === 0) {
                $this->comment('Queue finished processing. Sleeping at '.$now->toDateTimeString());

                Sleep::for(1)->second();
            }

            $this->comment('Processed ['.$processed.'] entries at '.$now->toDateTimeString());
        }
    }
}
