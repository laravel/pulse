<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return Config::get('pulse.storage.database.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // TODO Do another pass at the indexes to ensure that they are optimized correctly.
        Schema::create('pulse_system_stats', function (Blueprint $table) {
            $table->datetime('date');
            $table->string('server');
            $table->unsignedTinyInteger('cpu_percent');
            $table->unsignedInteger('memory_used');
            $table->unsignedInteger('memory_total');
            $table->json('storage');

            $table->index(['server', 'date']); // todo review once Forge is cranking with this.
        });

        Schema::create('pulse_requests', function (Blueprint $table) {
            $table->datetime('date');
            $table->string('user_id')->nullable();
            $table->text('route');
            $table->char('route_hash', 16)->charset('binary')->virtualAs('UNHEX(MD5(`route`))');
            $table->unsignedInteger('duration');
            $table->boolean('slow');

            $table->index([
                'date',    // usage:request_counts + trim
                'user_id', // usage:request_counts
            ]);
            $table->index(['user_id', 'date']); // usage:request_counts
            $table->index(['slow', 'date', 'user_id']); // usage:slow_endpoint_counts
            $table->index(['route_hash']); // slow_endpoints
            $table->index(['slow', 'date', 'route_hash', 'duration']); // slow_endpoints
        });

        Schema::create('pulse_exceptions', function (Blueprint $table) {
            $table->datetime('date');
            $table->string('user_id')->nullable();
            $table->text('class');
            $table->text('location');
            $table->char('class_location_hash', 16)->charset('binary')->virtualAs('UNHEX(MD5(CONCAT(`class`, `location`)))');

            $table->index(['class_location_hash']); // exceptions
            $table->index([
                'date',                // exceptions, trim
                'class_location_hash', // exceptions
            ]);
        });

        Schema::create('pulse_slow_queries', function (Blueprint $table) {
            $table->datetime('date');
            $table->string('user_id')->nullable();
            $table->text('sql');
            $table->text('location');
            $table->char('sql_location_hash', 16)->charset('binary')->virtualAs('UNHEX(MD5(CONCAT(`sql`, `location`)))');
            $table->unsignedInteger('duration');

            $table->index(['sql_location_hash']); // slow_queries
            $table->index([
                'date', // slow_queries, trim
                'sql_location_hash', // slow_queries
                'duration', // slow_queries
            ]);
        });

        Schema::create('pulse_jobs', function (Blueprint $table) {
            $table->datetime('date');
            $table->string('user_id')->nullable();
            $table->text('job');
            $table->char('job_hash', 16)->charset('binary')->virtualAs('UNHEX(MD5(`job`))');
            $table->uuid('job_uuid');
            $table->unsignedInteger('attempt');
            $table->string('connection');
            $table->string('queue');
            $table->datetime('queued_at');
            $table->datetime('processing_at')->nullable();
            $table->datetime('released_at')->nullable();
            $table->datetime('processed_at')->nullable();
            $table->datetime('failed_at')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->boolean('slow')->default(false);

            // TODO: verify this update index. Needs to find job quickly. does attempts have any benefit here?
            $table->index(['job_uuid']);

            $table->index(['date']); // trim
            $table->index(['job_hash']); // slow_jobs
            $table->index(['slow', 'date', 'job_hash', 'duration']); // slow_jobs
            $table->index(['queued_at', 'user_id']); // user_usage
        });

        Schema::create('pulse_cache_interactions', function (Blueprint $table) {
            $table->datetime('date');
            $table->string('user_id')->nullable();
            $table->text('key');
            $table->char('key_hash', 16)->charset('binary')->virtualAs('UNHEX(MD5(`key`))');
            $table->boolean('hit');

            $table->index(['date', 'key_hash', 'hit']);
        });

        Schema::create('pulse_outgoing_requests', function (Blueprint $table) {
            $table->datetime('date');
            $table->string('user_id')->nullable();
            $table->text('uri');
            $table->char('uri_hash', 16)->charset('binary')->virtualAs('UNHEX(MD5(`uri`))');
            $table->unsignedInteger('duration');
            $table->boolean('slow');

            $table->index(['date']); // trim
            $table->index(['uri_hash']); // slow_outgoing_requests
            $table->index(['slow', 'date', 'uri_hash', 'duration']); // slow_outgoing_requests
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pulse_system_stats');
        Schema::dropIfExists('pulse_requests');
        Schema::dropIfExists('pulse_exceptions');
        Schema::dropIfExists('pulse_slow_queries');
        Schema::dropIfExists('pulse_jobs');
        Schema::dropIfExists('pulse_cache_interactions');
        Schema::dropIfExists('pulse_outgoing_requests');
    }
};
