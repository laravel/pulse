<?php

namespace Laravel\Pulse;

use Illuminate\Contracts\Http\Kernel;
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
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public static function listenForStorageOpportunities($app)
    {
        static::storeEntriesBeforeTermination($app);
        static::storeEntriesAfterWorkerLoop($app);
    }

    /**
     * Store the entries in queue before the application termination.
     *
     * This handles storing entries for HTTP requests and Artisan commands.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected static function storeEntriesBeforeTermination($app)
    {
        $app[Kernel::class]->whenRequestLifecycleIsLongerThan(0, function () use ($app) {
            $app[Pulse::class]->store();
        });
    }

    /**
     * Store entries after the queue worker loops.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected static function storeEntriesAfterWorkerLoop($app)
    {
        $app['events']->listen(JobProcessing::class, function ($event) {
            if ($event->connectionName !== 'sync') {
                static::$processingJobs[] = true;
            }
        });

        $app['events']->listen(JobProcessed::class, function ($event) use ($app) {
            static::storeIfDoneProcessingJob($event, $app);
        });

        $app['events']->listen(JobFailed::class, function ($event) use ($app) {
            static::storeIfDoneProcessingJob($event, $app);
        });

        $app['events']->listen(JobExceptionOccurred::class, function () {
            array_pop(static::$processingJobs);
        });
    }

    /**
     * Store the recorded entries if totally done processing the current job.
     *
     * @param  \Illuminate\Queue\Events\JobProcessed  $event
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected static function storeIfDoneProcessingJob($event, $app)
    {
        array_pop(static::$processingJobs);

        if (empty(static::$processingJobs) && $event->connectionName !== 'sync') {
            $app[Pulse::class]->store();
        }
    }
}
