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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pulse_requests');
    }
};
