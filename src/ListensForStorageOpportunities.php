<?php

namespace Laravel\Pulse;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

trait ListensForStorageOpportunities
{
    /**
     * An array indicating how many jobs are processing.
     *
     * @var array<int, bool>
     */
    protected static array $processingJobs = [];

    /**
     * Register listeners that store the recorded Telescope entries.
     */
    public static function listenForStorageOpportunities(Application $app): void
    {
        static::storeEntriesBeforeTermination($app);
        static::storeEntriesAfterWorkerLoop($app);
    }

    /**
     * Store the entries in queue before the application termination.
     *
     * This handles storing entries for HTTP requests and Artisan commands.
     */
    protected static function storeEntriesBeforeTermination(Application $app): void
    {
        $app[Kernel::class]->whenRequestLifecycleIsLongerThan(0, function () use ($app) {
            // TODO; this will go stale
            $app[Pulse::class]->store();
        });
    }

    /**
     * Store entries after the queue worker loops.
     */
    protected static function storeEntriesAfterWorkerLoop(Application $app): void
    {
        $app['events']->listen(JobProcessing::class, function (JobProcessing $event) {
            if ($event->connectionName !== 'sync') {
                static::$processingJobs[] = true;
            }
        });

        $app['events']->listen(JobProcessed::class, function (JobProcessed $event) use ($app) {
            static::storeIfDoneProcessingJob($event, $app);
        });

        $app['events']->listen(JobFailed::class, function (JobFailed $event) use ($app) {
            static::storeIfDoneProcessingJob($event, $app);
        });

        $app['events']->listen(JobExceptionOccurred::class, function () {
            array_pop(static::$processingJobs);
        });
    }

    /**
     * Store the recorded entries if totally done processing the current job.
     */
    protected static function storeIfDoneProcessingJob(JobProcessed|JobFailed $event, Application $app): void
    {
        array_pop(static::$processingJobs);

        if (empty(static::$processingJobs) && $event->connectionName !== 'sync') {
            // TODO: this will go stale
            $app[Pulse::class]->store();
        }
    }
}
