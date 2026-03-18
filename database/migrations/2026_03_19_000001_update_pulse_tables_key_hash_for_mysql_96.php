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

        if ($this->driver() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('pulse_values')) {
            Schema::table('pulse_values', function (Blueprint $table) {
                $table->dropUnique(['type', 'key_hash']);
                $table->dropColumn('key_hash');
            });

            Schema::table('pulse_values', function (Blueprint $table) {
                $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(substr(sha2(`key`, 256), 1, 32))')->after('key');
                $table->unique(['type', 'key_hash']);
            });
        }

        if (Schema::hasTable('pulse_entries')) {
            Schema::table('pulse_entries', function (Blueprint $table) {
                $table->dropIndex(['key_hash']);
                $table->dropIndex(['timestamp', 'type', 'key_hash', 'value']);
                $table->dropColumn('key_hash');
            });

            Schema::table('pulse_entries', function (Blueprint $table) {
                $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(substr(sha2(`key`, 256), 1, 32))')->after('key');
                $table->index('key_hash');
                $table->index(['timestamp', 'type', 'key_hash', 'value']);
            });
        }

        if (Schema::hasTable('pulse_aggregates')) {
            Schema::table('pulse_aggregates', function (Blueprint $table) {
                $table->dropUnique(['bucket', 'period', 'type', 'aggregate', 'key_hash']);
                $table->dropColumn('key_hash');
            });

            Schema::table('pulse_aggregates', function (Blueprint $table) {
                $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(substr(sha2(`key`, 256), 1, 32))')->after('key');
                $table->unique(['bucket', 'period', 'type', 'aggregate', 'key_hash']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->driver() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('pulse_values')) {
            Schema::table('pulse_values', function (Blueprint $table) {
                $table->dropUnique(['type', 'key_hash']);
                $table->dropColumn('key_hash');
            });

            Schema::table('pulse_values', function (Blueprint $table) {
                $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))')->after('key');
                $table->unique(['type', 'key_hash']);
            });
        }

        if (Schema::hasTable('pulse_entries')) {
            Schema::table('pulse_entries', function (Blueprint $table) {
                $table->dropIndex(['key_hash']);
                $table->dropIndex(['timestamp', 'type', 'key_hash', 'value']);
                $table->dropColumn('key_hash');
            });

            Schema::table('pulse_entries', function (Blueprint $table) {
                $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))')->after('key');
                $table->index('key_hash');
                $table->index(['timestamp', 'type', 'key_hash', 'value']);
            });
        }

        if (Schema::hasTable('pulse_aggregates')) {
            Schema::table('pulse_aggregates', function (Blueprint $table) {
                $table->dropUnique(['bucket', 'period', 'type', 'aggregate', 'key_hash']);
                $table->dropColumn('key_hash');
            });

            Schema::table('pulse_aggregates', function (Blueprint $table) {
                $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))')->after('key');
                $table->unique(['bucket', 'period', 'type', 'aggregate', 'key_hash']);
            });
        }
    }
};
