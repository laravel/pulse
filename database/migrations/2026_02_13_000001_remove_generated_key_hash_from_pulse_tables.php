<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Support\PulseMigration;

return new class extends PulseMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->shouldRun()) {
            return;
        }

        foreach (['pulse_values', 'pulse_entries', 'pulse_aggregates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                match ($this->driver()) {
                    'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->change(),
                    'pgsql' => $table->uuid('key_hash')->change(),
                    'sqlite' => $table->string('key_hash')->change(),
                };
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->shouldRun()) {
            return;
        }

        foreach (['pulse_values', 'pulse_entries', 'pulse_aggregates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                match ($this->driver()) {
                    'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))')->change(),
                    'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid')->change(),
                    'sqlite' => $table->string('key_hash')->change(),
                };
            });
        }
    }
};
