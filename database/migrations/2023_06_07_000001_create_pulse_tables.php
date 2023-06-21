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

            $table->index(['date', 'user_id'], 'user_usage');
            $table->index(['date', 'route', 'duration'], 'slow_endpoints');
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

            $table->index(['date', 'sql', 'duration'], 'slow_queries');
        });

        Schema::create('pulse_jobs', function (Blueprint $table) {
            $table->timestamp('date');
            $table->string('user_id')->nullable();
            // $table->string('job');
            // $table->string('job_id');
            // $table->unsignedInteger('duration')->nullable();

            $table->index(['date', 'user_id'], 'user_usage');
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
    }
};
