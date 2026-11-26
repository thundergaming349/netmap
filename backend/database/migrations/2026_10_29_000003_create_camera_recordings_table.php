<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camera_recordings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('camera_id')->constrained('cameras')->cascadeOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at');
            $table->unsignedInteger('duration_seconds')->default(3600);
            $table->string('filename');
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 32)->default('ready');
            $table->timestampsTz();
            $table->unique(['camera_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_recordings');
    }
};
