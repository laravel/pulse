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
            $table->string('key');
            $table->text('value');
            // $table->unsignedInteger('updated');
            // $table->unsignedInteger('expires')->nullable();

            $table->unique('key');
            // $table->index('expires');
        });

        Schema::create('pulse_entries', function (Blueprint $table) {
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->text('key');
            $table->char('key_hash', 16)->charset('binary')->virtualAs('UNHEX(MD5(`key`))');
            $table->unsignedInteger('value')->nullable();

            $table->index(['timestamp', 'type', 'key_hash', 'value']); // TODO: This is a guess.
        });

        Schema::create('pulse_aggregates', function (Blueprint $table) {
            $table->unsignedInteger('bucket');
            $table->unsignedMediumInteger('period');
            $table->string('type');
            $table->text('key');
            $table->char('key_hash', 16)->charset('binary')->virtualAs('UNHEX(MD5(`key`))');
            $table->unsignedInteger('value');
            $table->unsignedInteger('count')->nullable();

            $table->unique(['bucket', 'period', 'type', 'key_hash']);

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
