<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_code', 50)->unique();
            $table->string('device_name', 100);
            $table->text('description')->nullable();
            $table->string('location', 150)->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'MAINTENANCE'])->default('ACTIVE')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->uuid('inspection_id')->unique();
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->timestamp('inspected_at')->index();
            $table->timestamp('received_at')->nullable();
            $table->enum('overall_status', ['PASS', 'DEFECT'])->index();
            $table->unsignedSmallInteger('defect_count')->default(0);
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->string('model_version', 50);
            $table->string('image_path')->nullable();
            $table->string('image_mime', 50)->nullable();
            $table->unsignedInteger('image_size_bytes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'inspected_at']);
            $table->index(['overall_status', 'inspected_at']);
        });

        Schema::create('inspection_detections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('detection_index')->default(0);
            $table->string('defect_class', 50)->index();
            $table->decimal('confidence', 5, 4);
            $table->unsignedInteger('bbox_x')->nullable();
            $table->unsignedInteger('bbox_y')->nullable();
            $table->unsignedInteger('bbox_width')->nullable();
            $table->unsignedInteger('bbox_height')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['inspection_id', 'detection_index']);
        });

        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id');
            $table->enum('source_type', ['CLOUD', 'NETWORK'])->index();
            $table->string('model_name', 100);
            $table->string('model_version', 50);
            $table->unsignedInteger('forecast_horizon_minutes');
            $table->timestamp('generated_at')->index();
            $table->timestamp('received_at')->nullable();
            $table->enum('status', ['ACTIVE', 'SUPERSEDED', 'EXPIRED'])->default('ACTIVE')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'external_id']);
            $table->index(['source_type', 'generated_at']);
            $table->index(['source_type', 'status', 'generated_at']);
        });

        Schema::create('forecast_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_id')->constrained()->cascadeOnDelete();
            $table->string('metric_name', 50);
            $table->string('metric_unit', 20)->nullable();
            $table->decimal('predicted_value', 20, 6);
            $table->decimal('actual_value', 20, 6)->nullable();
            $table->timestamp('predicted_at');
            $table->decimal('anomaly_risk', 5, 4)->nullable();
            $table->enum('risk_level', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['forecast_id', 'metric_name', 'predicted_at'], 'forecast_predictions_metric_unique');
            $table->index(['metric_name', 'predicted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_predictions');
        Schema::dropIfExists('forecasts');
        Schema::dropIfExists('inspection_detections');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('devices');
    }
};
