<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
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

        $lastTrimmedStorageAt = (new CarbonImmutable)->startOfMinute();

        while (true) {
            $now = new CarbonImmutable;

            if (Cache::get('illuminate:pulse:restart') !== $lastRestart) {
                return self::SUCCESS;
            }

            if ($now->subMinute()->greaterThan($lastTrimmedStorageAt)) {
                $storage->trim();

                $lastTrimmedStorageAt = $now;

                $this->comment('Storage trimmed');
            }

            $processed = $ingest->store($storage, 1000);

            if ($processed === 0) {
                Sleep::for(1)->second();
            } else {
                $this->comment('Processed ['.$processed.'] entries at '.$now->toDateTimeString());
            }
        }
    }
}
