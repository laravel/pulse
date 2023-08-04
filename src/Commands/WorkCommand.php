<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
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
    public function handle(Redis $ingest): void
    {
        while (true) {
            $persisted = $ingest->processEntries(1000);

            if ($persisted === 0) {
                $this->comment('Queue finished processing. Sleeping at '.now()->toDateTimeString());

                Sleep::for(1)->second();
            }

            $this->comment('Processed ['.$persisted.'] entries at '.now()->toDateTimeString());
        }
    }
}
