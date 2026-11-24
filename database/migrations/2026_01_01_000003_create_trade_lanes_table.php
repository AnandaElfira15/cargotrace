<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A trade lane carries the "group scenario" parameters (Group A / B / C of the brief):
        // route, delay thresholds, main exception type and the KPI to highlight.
        Schema::create('trade_lanes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();                   // A, B, C
            $table->string('name');                                 // Group A
            $table->string('route');                                // Southeast Asia -> Europe
            $table->decimal('delay_low_max_hours', 6, 2)->nullable();    // null = use the global setting
            $table->decimal('delay_medium_max_hours', 6, 2)->nullable(); // null = use the global setting
            $table->string('primary_exception', 30);                // ExceptionType value
            $table->string('kpi', 40);                              // on_time_delivery_rate | average_delay_duration | exception_resolution_time
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_lanes');
    }
};
