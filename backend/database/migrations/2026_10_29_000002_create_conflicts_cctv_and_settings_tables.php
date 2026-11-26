<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflict_events', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->string('severity', 32);
            $table->string('status', 32)->default('active');
            $table->foreignId('segment_id')->constrained('segments')->cascadeOnDelete();
            $table->ipAddress('ip');
            $table->jsonb('macs');
            $table->string('title');
            $table->text('summary');
            $table->text('suggestion')->nullable();
            $table->string('source', 64)->default('arp_history');
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestampTz('opened_at');
            $table->timestampTz('last_detected_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestampTz('notified_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("CREATE UNIQUE INDEX conflict_events_active_unique ON conflict_events (type, segment_id, ip) WHERE status = 'active'");

        Schema::create('conflict_event_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conflict_event_id')->constrained('conflict_events')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('logged_at');
            $table->text('message');
            $table->jsonb('meta')->nullable();
        });

        Schema::create('nvrs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('name');
            $table->ipAddress('ip');
            $table->string('brand', 32)->default('hikvision');
            $table->string('web_url')->nullable();
            $table->unsignedSmallInteger('channel_count')->default(32);
            $table->unsignedBigInteger('capacity_bytes')->default(0);
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->unsignedSmallInteger('retention_days')->default(14);
            $table->string('status', 32)->default('online');
            $table->timestampTz('last_ok_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('cameras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('nvr_id')->nullable()->constrained('nvrs')->nullOnDelete();
            $table->unsignedSmallInteger('channel')->default(1);
            $table->string('brand', 32)->default('hikvision');
            $table->string('location_group', 64);
            $table->string('stream_key', 64)->unique();
            $table->string('stream_main')->nullable();
            $table->string('stream_sub')->nullable();
            $table->string('status', 32)->default('online');
            $table->unsignedSmallInteger('fps')->default(25);
            $table->string('resolution', 32)->default('1920x1080');
            $table->timestampTz('last_ok_at')->nullable();
            $table->timestampTz('last_snapshot_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 64);
            $table->string('key', 64);
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestampsTz();
            $table->unique(['group', 'key']);
        });

        Schema::create('backups', function (Blueprint $table): void {
            $table->id();
            $table->string('filename');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('includes_mikrotik_export')->default(false);
            $table->string('status', 32)->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('cameras');
        Schema::dropIfExists('nvrs');
        Schema::dropIfExists('conflict_event_logs');
        Schema::dropIfExists('conflict_events');
    }
};
