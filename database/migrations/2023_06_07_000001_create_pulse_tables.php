<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // TODO:
        // - Review column types. Most of these likely need to be a text column, even "route".
        // - We may need to keep a hashed version of the text columns to index and group by.
        // - Do another pass at the indexes to ensure that they are optimized correctly.
        Schema::create('pulse_servers', function (Blueprint $table) {
            $table->timestamp('date');
            $table->string('server');
            $table->unsignedTinyInteger('cpu_percent');
            $table->unsignedInteger('memory_used');
            $table->unsignedInteger('memory_total');
            $table->json('storage');

            $table->index(['server', 'date']);
        });

        Schema::create('pulse_requests', function (Blueprint $table) {
            $table->timestamp('date');
            $table->string('user_id')->nullable();
            $table->string('route');
            $table->unsignedInteger('duration');

            $table->index(['date', 'user_id']); // user_usage
            $table->index(['date', 'route', 'duration']); // slow_endpoints
        });

        Schema::create('pulse_exceptions', function (Blueprint $table) {
            $table->timestamp('date');
            $table->string('user_id')->nullable();
            $table->string('class');
            $table->string('location');

            $table->index(['date', 'class', 'location']);
        });

        Schema::create('pulse_queries', function (Blueprint $table) {
            $table->timestamp('date');
            $table->string('user_id')->nullable();
            $table->string('sql');
            $table->unsignedInteger('duration');

            $table->index(['date', 'sql', 'duration']); // slow_queries
        });

        Schema::create('pulse_jobs', function (Blueprint $table) {
            $table->timestamp('date');
            $table->string('user_id')->nullable();
            $table->string('job');
            $table->string('job_id');
            $table->timestamp('processing_started_at', 3)->nullable();
            $table->unsignedInteger('duration')->nullable();

            // TODO: verify this update index. Needs to find job quickly.
            $table->index(['job_id']);
            $table->index(['date', 'job', 'duration']); // slow_jobs
            $table->index(['date', 'user_id']); // user_usage
        });

        Schema::create('pulse_cache_hits', function (Blueprint $table) {
            $table->timestamp('date');
            $table->string('key');
            $table->boolean('hit');
            $table->string('user_id')->nullable();
            // TODO: indexes?
        });

        Schema::create('pulse_outgoing_requests', function (Blueprint $table) {
            $table->timestamp('date');
            $table->string('uri');
            $table->unsignedInteger('duration');
            $table->string('user_id')->nullable();
            // TODO: indexes?
        });

        Schema::create('pulse_queue_sizes', function (Blueprint $table) {
            $table->timestamp('date');
            $table->string('queue');
            $table->unsignedInteger('size');
            $table->unsignedInteger('failed');
            // TODO: indexes?
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pulse_servers');
        Schema::dropIfExists('pulse_requests');
        Schema::dropIfExists('pulse_exceptions');
        Schema::dropIfExists('pulse_queries');
        Schema::dropIfExists('pulse_jobs');
        Schema::dropIfExists('pulse_cache_hits');
        Schema::dropIfExists('pulse_outgoing_requests');
        Schema::dropIfExists('pulse_queue_sizes');
    }
};
