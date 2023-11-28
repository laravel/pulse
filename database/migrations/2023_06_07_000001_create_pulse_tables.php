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
        Schema::create('pulse_values', function (Blueprint $table) {
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->string('key');
            $table->text('value');

            $table->index('timestamp'); // For trimming
            $table->index('type'); // For fast lookups and purging.
            $table->unique(['type', 'key']); // For data integrity
        });

        Schema::create('pulse_entries', function (Blueprint $table) {
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->text('key');
            $table->char('key_hash', 16)->charset('binary')->virtualAs('UNHEX(MD5(`key`))');
            $table->unsignedInteger('value')->nullable();

            $table->index('timestamp'); // For trimming
            $table->index('type'); // For purging.
            $table->index(['timestamp', 'type', 'key_hash', 'value']); // TODO: This is a guess.
        });

        Schema::create('pulse_aggregates', function (Blueprint $table) {
            $table->unsignedInteger('bucket');
            $table->unsignedMediumInteger('period');
            $table->string('type');
            $table->text('key');
            $table->char('key_hash', 16)->charset('binary')->virtualAs('UNHEX(MD5(`key`))');
            $table->string('aggregate');
            $table->unsignedInteger('value');
            $table->unsignedInteger('count')->nullable();

            $table->unique(['bucket', 'period', 'type', 'aggregate', 'key_hash']); // Force "on duplicate update"
            $table->index(['period', 'bucket']); // For trimming
            $table->index('type'); // For purging.
            $table->index(['period', 'bucket', 'type']); // TODO: This is a guess.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pulse_values');
        Schema::dropIfExists('pulse_entries');
        Schema::dropIfExists('pulse_aggregates');
    }
};
