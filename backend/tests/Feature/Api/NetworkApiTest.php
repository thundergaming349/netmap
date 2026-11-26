<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Camera;
use App\Models\ConflictEvent;
use App\Models\Device;
use App\Models\Router;
use App\Models\Segment;
use App\Models\User;
use Database\Seeders\DemoSeeder;

function activeUser(Role $role): User
{
    return User::factory()->active()->withRole($role)->create();
}

beforeEach(function (): void {
    $this->seed(DemoSeeder::class);
});

it('returns dashboard summary with stats, segments, warnings, and uplinks', function (): void {
    $user = activeUser(Role::Technician);

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'router' => ['id', 'name', 'host', 'version', 'connected'],
                'stats' => ['used_ips', 'active_segments', 'cameras_online', 'active_conflicts'],
                'warnings',
                'segments',
                'uplinks',
                'activities',
            ],
        ])
        ->assertJsonPath('data.stats.active_segments', 4);
});

it('forbids cctv_viewer from accessing network endpoints', function (): void {
    $viewer = activeUser(Role::CctvViewer);

    $this->actingAs($viewer)->getJson('/api/v1/dashboard/summary')->assertForbidden();
    $this->actingAs($viewer)->getJson('/api/v1/segments')->assertForbidden();
    $this->actingAs($viewer)->getJson('/api/v1/devices')->assertForbidden();
    $this->actingAs($viewer)->getJson('/api/v1/cameras')->assertOk();
});

it('returns 256 ip map cells and next free ip for a segment', function (): void {
    $user = activeUser(Role::Technician);
    $cctv = Segment::query()->where('slug', 'cctv')->firstOrFail();

    $response = $this->actingAs($user)
        ->getJson("/api/v1/segments/{$cctv->id}/ip-map")
        ->assertOk();

    expect($response->json('data.cells'))->toHaveCount(256)
        ->and($response->json('data.cells.0.status'))->toBe('network')
        ->and($response->json('data.cells.1.status'))->toBe('gateway')
        ->and($response->json('data.cells.31.status'))->toBe('conflict')
        ->and($response->json('data.cells.40.status'))->toBe('reserved')
        ->and($response->json('data.summary.next_free_ip'))->toBe('192.168.20.50');

    $this->actingAs($user)
        ->getJson("/api/v1/segments/{$cctv->id}/next-free-ip?range=50-99")
        ->assertOk()
        ->assertJsonPath('data.ip', '192.168.20.50');
});

it('checks ip availability across free, used, reserved, in_pool, gateway, and out_of_segment', function (): void {
    $user = activeUser(Role::Technician);

    $this->actingAs($user)
        ->getJson('/api/v1/ip-check?ip=192.168.20.50')
        ->assertOk()
        ->assertJsonPath('data.result', 'free')
        ->assertJsonPath('data.safe', true);

    $this->actingAs($user)
        ->getJson('/api/v1/ip-check?ip=192.168.20.11')
        ->assertOk()
        ->assertJsonPath('data.result', 'used')
        ->assertJsonPath('data.safe', false)
        ->assertJsonPath('data.suggestion', '192.168.20.50');

    $this->actingAs($user)
        ->getJson('/api/v1/ip-check?ip=192.168.20.45')
        ->assertOk()
        ->assertJsonPath('data.result', 'reserved');

    $this->actingAs($user)
        ->getJson('/api/v1/ip-check?ip=192.168.10.150')
        ->assertOk()
        ->assertJsonPath('data.result', 'in_pool')
        ->assertJsonPath('data.safe', false);

    $this->actingAs($user)
        ->getJson('/api/v1/ip-check?ip=192.168.20.1')
        ->assertOk()
        ->assertJsonPath('data.result', 'gateway');

    $this->actingAs($user)
        ->getJson('/api/v1/ip-check?ip=172.16.99.10')
        ->assertOk()
        ->assertJsonPath('data.result', 'out_of_segment');
});

it('creates a device, normalizes MAC, and rejects duplicate IPs with 409 and a suggestion', function (): void {
    $tech = activeUser(Role::Technician);
    $cctv = Segment::query()->where('slug', 'cctv')->firstOrFail();

    $this->actingAs($tech)
        ->postJson('/api/v1/devices', [
            'name' => 'Cam-Gudang-05',
            'type' => 'camera',
            'segment_id' => $cctv->id,
            'ip' => '192.168.20.50',
            'mac' => 'BC-32-5F-AA-BB-50',
            'location' => 'Gudang',
            'port' => 'port 9',
        ])
        ->assertCreated()
        ->assertJsonPath('data.mac', 'bc:32:5f:aa:bb:50');

    // Duplicate IP on same segment returns 409 + suggestion
    $this->actingAs($tech)
        ->postJson('/api/v1/devices', [
            'name' => 'Cam-Duplikat',
            'type' => 'camera',
            'segment_id' => $cctv->id,
            'ip' => '192.168.20.50',
            'mac' => 'BC:32:5F:AA:BB:51',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'ip_already_used')
        ->assertJsonPath('suggestion', '192.168.20.51');
});

it('creates reservations and prevents overlapping ranges', function (): void {
    $tech = activeUser(Role::Technician);
    $cctv = Segment::query()->where('slug', 'cctv')->firstOrFail();

    $this->actingAs($tech)
        ->postJson('/api/v1/ip-reservations', [
            'segment_id' => $cctv->id,
            'start_octet' => 80,
            'end_octet' => 89,
            'label' => 'Kamera Area Parkir Timur',
        ])
        ->assertCreated()
        ->assertJsonPath('data.range_label', '.80–.89');

    // Overlapping with .40-.49 fails with 409
    $this->actingAs($tech)
        ->postJson('/api/v1/ip-reservations', [
            'segment_id' => $cctv->id,
            'start_octet' => 45,
            'end_octet' => 52,
            'label' => 'Overlap',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'reservation_overlap');
});

it('returns topology, routes, and device hop trace', function (): void {
    $tech = activeUser(Role::Technician);
    $cameraDevice = Device::query()->where('ip', '192.168.20.31')->firstOrFail();

    $this->actingAs($tech)
        ->getJson('/api/v1/topology')
        ->assertOk()
        ->assertJsonCount(4, 'data.lanes')
        ->assertJsonCount(2, 'data.uplinks');

    $this->actingAs($tech)
        ->getJson('/api/v1/routes')
        ->assertOk();

    $trace = $this->actingAs($tech)
        ->getJson("/api/v1/devices/{$cameraDevice->id}/trace")
        ->assertOk();

    expect(count($trace->json('data.hops')))->toBeGreaterThanOrEqual(4);
});

it('resolves an active conflict event and logs timeline entry', function (): void {
    $tech = activeUser(Role::Technician);
    $conflict = ConflictEvent::query()->active()->firstOrFail();

    $this->actingAs($tech)
        ->postJson("/api/v1/conflict-events/{$conflict->id}/resolve", [
            'note' => 'Laptop teknisi sudah dilepas dari port 12.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');
});

it('returns cameras, nvrs, and stream urls without exposing credentials', function (): void {
    $viewer = activeUser(Role::CctvViewer);
    $camera = Camera::query()->firstOrFail();

    $stream = $this->actingAs($viewer)
        ->getJson("/api/v1/cameras/{$camera->id}/stream?quality=main")
        ->assertOk();

    expect($stream->json('data.webrtc_url'))->toStartWith('/stream/')
        ->and($stream->json('data.webrtc_url'))->not->toContain('rtsp://');

    $this->actingAs($viewer)->getJson('/api/v1/nvrs')->assertOk()->assertJsonCount(2, 'data');
});

it('guards mikrotik write actions by permission and write flag', function (): void {
    $admin = activeUser(Role::Admin);
    $tech = activeUser(Role::Technician);
    $office = Segment::query()->where('slug', 'kantor')->firstOrFail();

    config(['netmap.mikrotik_write_enabled' => false]);

    $this->actingAs($admin)
        ->postJson('/api/v1/mikrotik/leases/make-static', [
            'segment_id' => $office->id,
            'ip' => '192.168.10.120',
            'mac' => '3C:2A:F4:99:10:78',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'write_disabled');

    config(['netmap.mikrotik_write_enabled' => true]);

    $this->actingAs($tech)
        ->postJson('/api/v1/mikrotik/leases/make-static', [
            'segment_id' => $office->id,
            'ip' => '192.168.10.120',
            'mac' => '3C:2A:F4:99:10:78',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->postJson('/api/v1/mikrotik/leases/make-static', [
            'segment_id' => $office->id,
            'ip' => '192.168.10.120',
            'mac' => '3C:2A:F4:99:10:78',
            'comment' => 'Printer-HRD-Lt2',
        ])
        ->assertOk();

    $this->actingAs($admin)
        ->postJson('/api/v1/mikrotik/mac-blocks', [
            'mac' => 'AA:BB:CC:DD:EE:11',
            'ip' => '192.168.20.31',
            'reason' => 'MAC bentrok dengan kamera gudang',
        ])
        ->assertCreated();
});

it('verifies webhook token and processes dhcp lease and netwatch webhooks', function (): void {
    $router = Router::query()->firstOrFail();

    $this->getJson('/webhook/mikrotik/lease?token=wrong&ip=192.168.10.155&mac=aa:bb:cc:dd:ee:ff')
        ->assertForbidden();

    $this->getJson("/webhook/mikrotik/lease?token={$router->webhook_token}&ip=192.168.10.155&mac=AA:BB:CC:DD:EE:55&bound=1&server=dhcp-kantor")
        ->assertStatus(202);

    $this->getJson("/webhook/mikrotik/netwatch?token={$router->webhook_token}&name=ISP-A&status=down")
        ->assertStatus(202);
});
