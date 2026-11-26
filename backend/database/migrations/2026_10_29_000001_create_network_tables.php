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
        Schema::create('routers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->ipAddress('host');
            $table->unsignedSmallInteger('api_port')->default(443);
            $table->boolean('use_ssl')->default(true);
            $table->string('webhook_token', 64)->nullable();
            $table->string('version')->nullable();
            $table->string('uptime')->nullable();
            $table->unsignedTinyInteger('cpu_load')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });

        Schema::create('segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('vlan_id')->nullable();
            $table->ipAddress('gateway');
            $table->string('interface');
            $table->ipAddress('pool_start')->nullable();
            $table->ipAddress('pool_end')->nullable();
            $table->unsignedSmallInteger('static_start')->default(2);
            $table->unsignedSmallInteger('static_end')->default(99);
            $table->string('egress')->nullable();
            $table->string('color', 32)->default('#4CC2FF');
            $table->boolean('is_isolated')->default(false);
            $table->string('bandwidth_limit', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE segments ADD COLUMN subnet cidr NOT NULL DEFAULT '192.168.0.0/24'::cidr");
        DB::statement('ALTER TABLE segments ALTER COLUMN subnet DROP DEFAULT');

        Schema::create('network_switches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->nullOnDelete();
            $table->string('name');
            $table->ipAddress('ip')->nullable();
            $table->macAddress('mac')->nullable();
            $table->string('uplink_port')->nullable();
            $table->unsignedSmallInteger('total_ports')->default(24);
            $table->boolean('is_managed')->default(false);
            $table->text('snmp_community')->nullable();
            $table->string('location')->nullable();
            $table->timestampsTz();
        });

        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('segment_id')->constrained('segments')->cascadeOnDelete();
            $table->foreignId('network_switch_id')->nullable()->constrained('network_switches')->nullOnDelete();
            $table->string('name');
            $table->string('type', 32);
            $table->string('status', 32)->default('online');
            $table->ipAddress('ip');
            $table->macAddress('mac')->nullable();
            $table->string('location')->nullable();
            $table->string('port')->nullable();
            $table->text('note')->nullable();
            $table->boolean('monitor_ping')->default(true);
            $table->boolean('is_static_lease')->default(false);
            $table->boolean('is_arp_pinned')->default(false);
            $table->timestampTz('last_seen_at')->nullable();
            $table->unsignedSmallInteger('latency_ms')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE INDEX devices_ip_gist ON devices USING gist (ip inet_ops)');
        DB::statement('CREATE UNIQUE INDEX devices_segment_ip_unique ON devices (segment_id, ip) WHERE deleted_at IS NULL');

        Schema::create('ip_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('segment_id')->constrained('segments')->cascadeOnDelete();
            $table->ipAddress('ip_start');
            $table->ipAddress('ip_end');
            $table->unsignedSmallInteger('start_octet');
            $table->unsignedSmallInteger('end_octet');
            $table->string('label');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('arp_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('segment_id')->constrained('segments')->cascadeOnDelete();
            $table->ipAddress('ip');
            $table->macAddress('mac');
            $table->string('interface')->nullable();
            $table->boolean('is_dynamic')->default(true);
            $table->boolean('is_complete')->default(true);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
        });

        DB::statement('CREATE INDEX arp_observations_ip_gist ON arp_observations USING gist (ip inet_ops)');
        DB::statement('CREATE UNIQUE INDEX arp_observations_unique ON arp_observations (segment_id, ip, mac)');
        DB::statement('CREATE INDEX arp_observations_last_seen ON arp_observations (last_seen_at)');

        Schema::create('dhcp_leases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('segment_id')->constrained('segments')->cascadeOnDelete();
            $table->ipAddress('ip');
            $table->macAddress('mac');
            $table->string('host_name')->nullable();
            $table->string('server')->nullable();
            $table->string('status', 32)->default('bound');
            $table->boolean('is_dynamic')->default(true);
            $table->boolean('is_present')->default(true);
            $table->string('routeros_id', 32)->nullable();
            $table->string('comment')->nullable();
            $table->timestampTz('last_seen_at');
            $table->timestampsTz();
            $table->unique(['segment_id', 'ip', 'mac']);
        });

        Schema::create('mac_locations', function (Blueprint $table): void {
            $table->id();
            $table->macAddress('mac')->unique();
            $table->foreignId('network_switch_id')->nullable()->constrained('network_switches')->nullOnDelete();
            $table->string('interface')->nullable();
            $table->string('port')->nullable();
            $table->string('source', 32)->default('bridge_host');
            $table->timestampTz('last_seen_at');
            $table->timestampsTz();
        });

        Schema::create('router_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('dst_address');
            $table->string('gateway');
            $table->string('routing_table', 64)->default('main');
            $table->unsignedSmallInteger('distance')->default(1);
            $table->unsignedSmallInteger('scope')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_dynamic')->default(false);
            $table->boolean('is_present')->default(true);
            $table->string('comment')->nullable();
            $table->jsonb('segment_ids')->nullable();
            $table->timestampsTz();
        });

        Schema::create('uplinks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('interface');
            $table->string('gateway');
            $table->string('check_target')->default('8.8.8.8');
            $table->unsignedSmallInteger('distance')->default(1);
            $table->string('status', 32)->default('up');
            $table->unsignedSmallInteger('latency_ms')->nullable();
            $table->unsignedInteger('rx_mbps')->default(0);
            $table->unsignedInteger('tx_mbps')->default(0);
            $table->timestampTz('last_changed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('mac_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->nullOnDelete();
            $table->macAddress('mac');
            $table->ipAddress('ip')->nullable();
            $table->string('reason')->nullable();
            $table->string('routeros_id', 32)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mac_blocks');
        Schema::dropIfExists('uplinks');
        Schema::dropIfExists('router_routes');
        Schema::dropIfExists('mac_locations');
        Schema::dropIfExists('dhcp_leases');
        Schema::dropIfExists('arp_observations');
        Schema::dropIfExists('ip_reservations');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('network_switches');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('routers');
    }
};
