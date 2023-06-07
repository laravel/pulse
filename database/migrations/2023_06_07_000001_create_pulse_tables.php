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
            $table->unsignedInteger('resolution')->index();
            $table->string('user_id')->nullable();
            $table->string('route');
            $table->unsignedInteger('volume');
            $table->unsignedInteger('average');
            $table->unsignedInteger('slowest');
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
