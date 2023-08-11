<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Ingests\Redis;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pulse:work')]
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
    public function handle(Ingest $ingest, Storage $storage): int
    {
        $lastRestart = Cache::get('illuminate:pulse:restart');

        $lastTrimmedDatabaseAt = (new CarbonImmutable)->startOfMinute();

        while (true) {
            $now = new CarbonImmutable;

            if (Cache::get('illuminate:pulse:restart') !== $lastRestart) {
                $this->comment('Pulse restart requested. Exiting at '.$now->toDateTimeString());

                return self::SUCCESS;
            }

            if ($now->subMinute()->greaterThan($lastTrimmedDatabaseAt)) {
                $this->comment('Trimming the database at '.$now->toDateTimeString());

                $storage->trim($now->subWeek());

                $lastTrimmedDatabaseAt = $now;
            }

            $processed = $ingest->store($storage, 1000);

            if ($processed === 0) {
                $this->comment('Queue finished processing. Sleeping at '.$now->toDateTimeString());

                Sleep::for(1)->second();
            } else {
                $this->comment('Processed ['.$processed.'] entries at '.$now->toDateTimeString());
            }
        }
    }
}
