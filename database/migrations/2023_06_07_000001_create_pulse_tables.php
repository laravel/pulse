<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        Schema::create('pulse_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->mediumText('key');
            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                'sqlite' => $table->string('key_hash'),
            };
            $table->mediumText('value');

            $table->index('timestamp'); // For trimming...
            $table->index('type'); // For fast lookups and purging...
            $table->unique(['type', 'key_hash']); // For data integrity and upserts...
        });

        if (in_array($this->driver(), ['mariadb', 'mysql'])) {
            DB::connection($this->getConnection())->unprepared('
                CREATE TRIGGER pulse_values_before_insert
                BEFORE INSERT ON pulse_values
                FOR EACH ROW
                SET NEW.key_hash = UNHEX(MD5(NEW.key))
            ');

            DB::connection($this->getConnection())->unprepared('
                CREATE TRIGGER pulse_values_before_update
                BEFORE UPDATE ON pulse_values
                FOR EACH ROW
                SET NEW.key_hash = UNHEX(MD5(NEW.key))
            ');
        }

        Schema::create('pulse_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->mediumText('key');
            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                'sqlite' => $table->string('key_hash'),
            };
            $table->bigInteger('value')->nullable();

            $table->index('timestamp'); // For trimming...
            $table->index('type'); // For purging...
            $table->index('key_hash'); // For mapping...
            $table->index(['timestamp', 'type', 'key_hash', 'value']); // For aggregate queries...
        });

        if (in_array($this->driver(), ['mariadb', 'mysql'])) {
            DB::connection($this->getConnection())->unprepared('
                CREATE TRIGGER pulse_entries_before_insert
                BEFORE INSERT ON pulse_entries
                FOR EACH ROW
                SET NEW.key_hash = UNHEX(MD5(NEW.key))
            ');

            DB::connection($this->getConnection())->unprepared('
                CREATE TRIGGER pulse_entries_before_update
                BEFORE UPDATE ON pulse_entries
                FOR EACH ROW
                SET NEW.key_hash = UNHEX(MD5(NEW.key))
            ');
        }

        Schema::create('pulse_aggregates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('bucket');
            $table->unsignedMediumInteger('period');
            $table->string('type');
            $table->mediumText('key');
            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                'sqlite' => $table->string('key_hash'),
            };
            $table->string('aggregate');
            $table->decimal('value', 20, 2);
            $table->unsignedInteger('count')->nullable();

            $table->unique(['bucket', 'period', 'type', 'aggregate', 'key_hash']); // Force "on duplicate update"...
            $table->index(['period', 'bucket']); // For trimming...
            $table->index('type'); // For purging...
            $table->index(['period', 'type', 'aggregate', 'bucket']); // For aggregate queries...
        });

        if (in_array($this->driver(), ['mariadb', 'mysql'])) {
            DB::connection($this->getConnection())->unprepared('
                CREATE TRIGGER pulse_aggregates_before_insert
                BEFORE INSERT ON pulse_aggregates
                FOR EACH ROW
                SET NEW.key_hash = UNHEX(MD5(NEW.key))
            ');

            DB::connection($this->getConnection())->unprepared('
                CREATE TRIGGER pulse_aggregates_before_update
                BEFORE UPDATE ON pulse_aggregates
                FOR EACH ROW
                SET NEW.key_hash = UNHEX(MD5(NEW.key))
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array($this->driver(), ['mariadb', 'mysql'])) {
            DB::connection($this->getConnection())->unprepared('DROP TRIGGER IF EXISTS pulse_values_before_insert');
            DB::connection($this->getConnection())->unprepared('DROP TRIGGER IF EXISTS pulse_values_before_update');
            DB::connection($this->getConnection())->unprepared('DROP TRIGGER IF EXISTS pulse_entries_before_insert');
            DB::connection($this->getConnection())->unprepared('DROP TRIGGER IF EXISTS pulse_entries_before_update');
            DB::connection($this->getConnection())->unprepared('DROP TRIGGER IF EXISTS pulse_aggregates_before_insert');
            DB::connection($this->getConnection())->unprepared('DROP TRIGGER IF EXISTS pulse_aggregates_before_update');
        }

        Schema::dropIfExists('pulse_values');
        Schema::dropIfExists('pulse_entries');
        Schema::dropIfExists('pulse_aggregates');
    }
};
