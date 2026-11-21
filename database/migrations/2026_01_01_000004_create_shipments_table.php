<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 30)->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('carrier_id')->constrained('carriers');
            $table->foreignId('origin_location_id')->constrained('locations');
            $table->foreignId('destination_location_id')->constrained('locations');
            $table->string('cargo_type', 100);
            $table->decimal('weight_kg', 12, 2);
            $table->decimal('volume_cbm', 12, 2);
            $table->dateTime('expected_departure_at');
            $table->dateTime('expected_arrival_at');
            $table->dateTime('estimated_arrival_at')->nullable(); // current ETA
            $table->dateTime('actual_arrival_at')->nullable();    // set when DELIVERED
            $table->string('status', 20)->default('CREATED');
            // Derived by EtaService and stored so dashboards/filters stay fast
            $table->boolean('is_delayed')->default(false);
            $table->decimal('delay_hours', 8, 2)->default(0);
            $table->string('delay_severity', 10)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('is_delayed');
            $table->index('expected_arrival_at');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
