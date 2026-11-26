<?php

declare(strict_types=1);

namespace App\Services\Mikrotik;

use App\Enums\CameraStatus;
use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Models\ArpObservation;
use App\Models\Camera;
use App\Models\Device;
use App\Models\DhcpLease;
use App\Models\MacLocation;
use App\Models\NetworkSwitch;
use App\Models\Nvr;
use App\Models\Router;
use App\Models\RouterRoute;
use App\Models\Segment;
use App\Services\Conflicts\RuleEngine;
use App\Support\NetworkFormat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class Collector
{
    private const SEGMENT_PALETTE = ['#4CC2FF', '#FF9A4D', '#B49BFF', '#5FD39A', '#FFC46B'];

    public function __construct(
        private readonly MikrotikClient $client,
        private readonly RuleEngine $ruleEngine,
    ) {}

    /**
     * @return array{router_id: int|null, synced_at: string, arp_updated: int, active_conflicts: int}
     */
    public function sync(): array
    {
        if (! app()->runningUnitTests()) {
            $lanIp = @shell_exec('ip -4 addr show enp0s25 2>/dev/null');
            if (is_string($lanIp) && ! str_contains($lanIp, '192.168.3.')) {
                @shell_exec('nmcli connection up "Wired connection 1" >/dev/null 2>&1');
            }
        }

        $router = Router::query()->first();
        $now = now()->toImmutable();
        $isLiveRouter = false;

        if ($router !== null) {
            try {
                $status = $this->client->testConnection($router);
                $isLiveRouter = $status['live'];
                $router->update([
                    'version' => $status['version'],
                    'uptime' => $status['uptime'],
                    'cpu_load' => $status['cpu_load'],
                    'last_synced_at' => $now,
                    'last_error' => null,
                ]);
            } catch (\Throwable $e) {
                $router->update([
                    'last_error' => $e->getMessage(),
                ]);
            }
        }

        $arpUpdated = 0;

        if ($router !== null && $isLiveRouter) {
            $this->syncLiveAddressesAndPools($router);
            $macToPort = $this->syncLiveBridgeHosts();
            $this->syncLiveLeases();
            $arpUpdated = $this->syncLiveArpAndSubnetProbe($now, $macToPort);
            $this->syncLiveRoutes($router);
        } else {
            // Demo / local mode: keep online device timestamps & ARP observations fresh.
            $onlineDevices = Device::query()->with('segment')->where('status', '!=', 'offline')->whereNotNull('mac')->get();

            foreach ($onlineDevices as $device) {
                $device->update(['last_seen_at' => $now]);

                if ($device->mac !== null) {
                    ArpObservation::query()->updateOrCreate(
                        [
                            'segment_id' => $device->segment_id,
                            'ip' => $device->ip,
                            'mac' => $device->mac,
                        ],
                        [
                            'interface' => $device->segment !== null ? $device->segment->interface : 'vlan',
                            'is_dynamic' => ! $device->is_arp_pinned,
                            'is_complete' => true,
                            'first_seen_at' => $device->created_at ?? $now,
                            'last_seen_at' => $now,
                        ],
                    );
                    $arpUpdated++;
                }
            }
        }

        $activeConflicts = $this->ruleEngine->evaluateAll();

        return [
            'router_id' => $router?->id,
            'synced_at' => $now->toIso8601ZuluString(),
            'arp_updated' => $arpUpdated,
            'active_conflicts' => $activeConflicts,
        ];
    }

    private function syncLiveAddressesAndPools(Router $router): void
    {
        $vlansByName = collect($this->client->vlans())->keyBy(fn (array $v) => (string) ($v['name'] ?? ''));
        $pools = $this->client->pools();
        $addresses = $this->client->addresses();
        $index = 0;

        foreach ($addresses as $addr) {
            if (($addr['disabled'] ?? 'false') === 'true') {
                continue;
            }

            $addressCidr = (string) ($addr['address'] ?? '');
            $networkIp = (string) ($addr['network'] ?? '');
            $interface = (string) ($addr['interface'] ?? '');

            if (! str_ends_with($addressCidr, '/24') || $networkIp === '' || $interface === '') {
                continue;
            }

            $gateway = explode('/', $addressCidr)[0];
            $subnet = "{$networkIp}/24";
            $prefix = implode('.', array_slice(explode('.', $networkIp), 0, 3)).'.';

            $poolStart = null;
            $poolEnd = null;
            foreach ($pools as $pool) {
                $ranges = (string) ($pool['ranges'] ?? '');
                if (str_starts_with($ranges, $prefix) && str_contains($ranges, '-')) {
                    [$pStart, $pEnd] = explode('-', explode(',', $ranges)[0]);
                    $poolStart = trim($pStart);
                    $poolEnd = trim($pEnd);
                    break;
                }
            }

            $vlanInfo = $vlansByName->get($interface);
            $vlanId = isset($vlanInfo['vlan-id']) ? (int) $vlanInfo['vlan-id'] : null;

            $existing = Segment::query()->where('gateway', $gateway)->first();
            if ($existing === null) {
                $name = isset($addr['comment']) && trim((string) $addr['comment']) !== ''
                    ? trim((string) $addr['comment'])
                    : ($interface === 'netmap' ? 'Jalur Utama & CCTV' : strtoupper($interface));
                $slug = Str::slug($name) ?: 'seg-'.($index + 1);

                Segment::create([
                    'router_id' => $router->id,
                    'name' => $name,
                    'slug' => $slug,
                    'vlan_id' => $vlanId,
                    'subnet' => $subnet,
                    'gateway' => $gateway,
                    'interface' => $interface,
                    'pool_start' => $poolStart,
                    'pool_end' => $poolEnd,
                    'static_start' => 2,
                    'static_end' => 199,
                    'color' => self::SEGMENT_PALETTE[$index % count(self::SEGMENT_PALETTE)],
                ]);
            } else {
                $existing->update([
                    'pool_start' => $poolStart ?? $existing->pool_start,
                    'pool_end' => $poolEnd ?? $existing->pool_end,
                    'interface' => $interface,
                ]);
            }
            $index++;
        }
    }

    /**
     * @return array<string, string> Map of lowercase MAC -> RouterOS bridge port (e.g. ether1, ether2)
     */
    private function syncLiveBridgeHosts(): array
    {
        $hosts = $this->client->bridgeHosts();
        $now = now();
        $map = [];

        foreach ($hosts as $item) {
            if (($item['local'] ?? 'false') === 'true') {
                continue;
            }

            $mac = NetworkFormat::normalizeMac((string) ($item['mac-address'] ?? ''));
            $port = (string) ($item['on-interface'] ?? $item['interface'] ?? '');

            if (! NetworkFormat::isValidMac($mac) || $port === '') {
                continue;
            }

            /** @var string $validMac */
            $validMac = $mac;
            $map[$validMac] = $port;

            MacLocation::query()->updateOrCreate(
                ['mac' => $validMac],
                [
                    'interface' => (string) ($item['bridge'] ?? 'netmap'),
                    'port' => $port,
                    'source' => 'bridge_host',
                    'last_seen_at' => $now,
                ],
            );
        }

        return $map;
    }

    private function syncLiveLeases(): void
    {
        $leases = $this->client->leases();
        if (empty($leases)) {
            return;
        }

        $segments = Segment::query()->get();
        $now = now();

        foreach ($leases as $item) {
            $ip = trim((string) ($item['address'] ?? ''));
            $macRaw = (string) ($item['mac-address'] ?? $item['active-mac-address'] ?? $item['src-mac-address'] ?? '');
            $mac = NetworkFormat::normalizeMac($macRaw);
            $status = (string) ($item['status'] ?? 'bound');

            if (! NetworkFormat::isValidIpv4NoLeadingZeros($ip) || ! NetworkFormat::isValidMac($mac)) {
                continue;
            }

            $segment = $segments->first(fn (Segment $s) => $s->containsIp($ip));
            if ($segment === null) {
                continue;
            }

            /** @var string $validMac */
            $validMac = $mac;

            DhcpLease::query()->updateOrCreate(
                [
                    'segment_id' => $segment->id,
                    'ip' => $ip,
                    'mac' => $validMac,
                ],
                [
                    'host_name' => isset($item['host-name']) ? (string) $item['host-name'] : ($status === 'conflict' ? 'DHCP Conflict' : null),
                    'server' => isset($item['server']) ? (string) $item['server'] : null,
                    'status' => $status,
                    'is_dynamic' => ($item['dynamic'] ?? 'true') === 'true',
                    'is_present' => true,
                    'routeros_id' => isset($item['.id']) ? (string) $item['.id'] : null,
                    'comment' => isset($item['comment']) ? (string) $item['comment'] : null,
                    'last_seen_at' => $now,
                ],
            );

            // If RouterOS DHCP server flagged this IP as "status=conflict", record the conflicting MAC observation!
            if ($status === 'conflict') {
                ArpObservation::query()->updateOrCreate(
                    [
                        'segment_id' => $segment->id,
                        'ip' => $ip,
                        'mac' => $validMac,
                    ],
                    [
                        'interface' => $segment->interface,
                        'is_dynamic' => true,
                        'is_complete' => true,
                        'first_seen_at' => $now,
                        'last_seen_at' => $now,
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, string>  $macToPort
     */
    private function syncLiveArpAndSubnetProbe(CarbonImmutable $now, array $macToPort): int
    {
        $segments = Segment::query()->get();
        $discoveredIpMac = [];

        // 1. Run a fast (~1.1s) parallel ICMP ping sweep + TCP/UDP probe across each segment's /24.
        //    This wakes silent hosts (like 192.168.3.150), measures real ping latency, and distinguishes
        //    truly reachable hosts from dead STALE ARP cache entries.
        $pingLatencies = [];
        foreach ($segments as $segment) {
            $prefix = $segment->networkPrefix();
            $escapedPrefix = escapeshellarg($prefix);
            $pingOut = @shell_exec("seq 1 254 | xargs -P 128 -I {} ping -c 1 -W 0.35 {$escapedPrefix}.{} 2>/dev/null | grep 'bytes from'");
            if (is_string($pingOut) && $pingOut !== '') {
                foreach (explode("\n", trim($pingOut)) as $line) {
                    if (preg_match('/bytes from (\d+\.\d+\.\d+\.\d+):.*time=([0-9.]+)\s*ms/', $line, $pm) === 1) {
                        $pingLatencies[$pm[1]] = max(1, (int) round((float) $pm[2]));
                    }
                }
            }

            for ($batchStart = 1; $batchStart <= 254; $batchStart += 64) {
                $sockets = [];
                $batchEnd = min(254, $batchStart + 63);
                for ($i = $batchStart; $i <= $batchEnd; $i++) {
                    $t = @stream_socket_client(
                        "tcp://{$prefix}.{$i}:80",
                        $errno2,
                        $errstr2,
                        0.01,
                        STREAM_CLIENT_ASYNC_CONNECT,
                    );
                    if ($t !== false) {
                        $sockets[] = $t;
                    }
                }
                usleep(20000);
                foreach ($sockets as $s) {
                    @fclose($s);
                }
            }
        }

        // 2. Read the local Linux kernel neighbor table (`ip neigh show`) for directly connected L2 segments.
        //    Only accept entries that replied to ping OR are actively REACHABLE/DELAY (ignoring dead STALE entries).
        $neighOutput = @shell_exec('ip -4 neigh show 2>/dev/null');
        if (is_string($neighOutput) && $neighOutput !== '') {
            foreach (explode("\n", trim($neighOutput)) as $line) {
                if (preg_match('/^(\d+\.\d+\.\d+\.\d+)\s+.*lladdr\s+([0-9a-fA-F:]{17})\s+(REACHABLE|STALE|DELAY|PROBE)/', trim($line), $m) === 1) {
                    $ip = $m[1];
                    $state = $m[3];
                    $isAliveNow = isset($pingLatencies[$ip]) || $state === 'REACHABLE' || $state === 'DELAY';
                    if (! $isAliveNow) {
                        continue;
                    }
                    $mac = NetworkFormat::normalizeMac($m[2]);
                    if (NetworkFormat::isValidIpv4NoLeadingZeros($ip) && NetworkFormat::isValidMac($mac)) {
                        /** @var string $validMac */
                        $validMac = $mac;
                        $discoveredIpMac[$ip][$validMac] = true;
                    }
                }
            }
        }

        // 3. Read MikroTik's /ip/arp table (only complete entries, and if on local L2 segment require ping/reachable confirmation)
        foreach ($this->client->arp() as $item) {
            if (($item['complete'] ?? 'true') === 'false' || ($item['invalid'] ?? 'false') === 'true') {
                continue;
            }
            $ip = trim((string) ($item['address'] ?? ''));
            $mac = NetworkFormat::normalizeMac((string) ($item['mac-address'] ?? ''));
            if (NetworkFormat::isValidIpv4NoLeadingZeros($ip) && NetworkFormat::isValidMac($mac)) {
                // If we already ran local L2 ping sweep on 192.168.3.0/24, only include MikroTik cached ARP if host is alive
                if (! empty($pingLatencies) && str_starts_with($ip, '192.168.3.') && ! isset($pingLatencies[$ip]) && ! isset($discoveredIpMac[$ip])) {
                    continue;
                }
                /** @var string $validMac */
                $validMac = $mac;
                $discoveredIpMac[$ip][$validMac] = ($item['dynamic'] ?? 'true') === 'true';
            }
        }

        $count = 0;
        $cctvSwitch = NetworkSwitch::query()->where('name', 'like', '%CCTV%')->first();
        $defaultNvr = Nvr::query()->first();
        $ipCatalog = $this->loadIpCatalog();

        foreach ($discoveredIpMac as $ip => $macMap) {
            $segment = $segments->first(fn (Segment $s) => $s->containsIp($ip));
            if ($segment === null) {
                continue;
            }

            foreach ($macMap as $mac => $isDynamic) {
                ArpObservation::query()->updateOrCreate(
                    [
                        'segment_id' => $segment->id,
                        'ip' => $ip,
                        'mac' => $mac,
                    ],
                    [
                        'interface' => $macToPort[$mac] ?? $segment->interface,
                        'is_dynamic' => $isDynamic,
                        'is_complete' => true,
                        'first_seen_at' => $now,
                        'last_seen_at' => $now,
                    ],
                );
                $count++;
            }

            // Skip gateway (.1) and NetMap host (.199) from auto-device registration
            if ($ip === $segment->gateway || str_ends_with($ip, '.199')) {
                continue;
            }

            $primaryMac = (string) array_key_first($macMap);
            $bridgePort = $macToPort[$primaryMac] ?? ($segment->interface === 'netmap' ? 'ether1' : $segment->interface);
            $octet = $segment->octetOf($ip) ?? 0;

            // Check if this IP is a dynamic DHCP lease (non-conflict)
            $isBoundDynamicLease = DhcpLease::query()
                ->where('segment_id', $segment->id)
                ->where('ip', $ip)
                ->where('status', 'bound')
                ->where('is_dynamic', true)
                ->exists();

            if ($isBoundDynamicLease) {
                continue;
            }

            $catalogEntry = $ipCatalog[$ip] ?? null;
            $device = Device::query()->where('segment_id', $segment->id)->where('ip', $ip)->first();
            if ($device === null) {
                $isCctvPort = $bridgePort === 'ether1';
                $isNvrEntry = $catalogEntry !== null && $catalogEntry['is_nvr'];
                $type = $isNvrEntry
                    ? DeviceType::Nvr
                    : ($catalogEntry !== null || $isCctvPort ? DeviceType::Camera : DeviceType::Pc);
                $paddedOctet = str_pad((string) $octet, 3, '0', STR_PAD_LEFT);
                $name = $catalogEntry !== null && $catalogEntry['name'] !== ''
                    ? $catalogEntry['name']
                    : ($isCctvPort ? "CCTV-Unit-{$paddedOctet}" : "Perangkat-LAN-{$paddedOctet}");
                $location = $catalogEntry !== null && $catalogEntry['location'] !== ''
                    ? $catalogEntry['location']
                    : ($isCctvPort ? 'Switch CCTV (Port 1)' : 'LAN (Port 2)');

                $device = Device::create([
                    'segment_id' => $segment->id,
                    'network_switch_id' => $isCctvPort ? $cctvSwitch?->id : null,
                    'name' => $name,
                    'type' => $type,
                    'status' => count($macMap) > 1 ? DeviceStatus::Conflict : DeviceStatus::Online,
                    'ip' => $ip,
                    'mac' => $primaryMac,
                    'location' => $location,
                    'port' => $bridgePort,
                    'note' => $catalogEntry !== null ? $catalogEntry['note'] : null,
                    'monitor_ping' => true,
                    'is_static_lease' => false,
                    'is_arp_pinned' => false,
                    'last_seen_at' => $now,
                    'latency_ms' => $pingLatencies[$ip] ?? 2,
                ]);

                if ($type === DeviceType::Camera) {
                    $brand = $this->detectCameraBrand($ip, $primaryMac);
                    $locationGroup = $catalogEntry !== null && $catalogEntry['section'] !== ''
                        ? $catalogEntry['section']
                        : ($octet < 50 ? 'SAKINAH & TK' : ($octet < 94 ? 'SD 13' : ($octet < 132 ? 'SMP 12' : 'YAPI & MASJID')));
                    Camera::query()->updateOrCreate(
                        ['device_id' => $device->id],
                        [
                            'nvr_id' => $defaultNvr?->id,
                            'channel' => $octet,
                            'brand' => $brand,
                            'location_group' => $locationGroup,
                            'stream_key' => "cam_3_{$octet}",
                            'stream_main' => $brand === 'hikvision'
                                ? "rtsp://{$ip}:554/Streaming/Channels/101"
                                : "rtsp://{$ip}:554/live/0/main",
                            'stream_sub' => $brand === 'hikvision'
                                ? "rtsp://{$ip}:554/Streaming/Channels/102"
                                : "rtsp://{$ip}:554/live/0/sub",
                            'status' => count($macMap) > 1 ? CameraStatus::Unstable : CameraStatus::Online,
                            'fps' => 25,
                            'resolution' => '1920x1080',
                            'last_ok_at' => $now,
                        ],
                    );
                }
            } else {
                $nextStatus = count($macMap) > 1 ? DeviceStatus::Conflict : DeviceStatus::Online;
                $updates = [
                    'mac' => $device->mac ?? $primaryMac,
                    'last_seen_at' => $now,
                    'port' => $bridgePort,
                    'latency_ms' => $pingLatencies[$ip] ?? $device->latency_ms ?? 2,
                    'status' => $nextStatus,
                ];
                if ($catalogEntry !== null) {
                    $updates['name'] = $catalogEntry['name'];
                    $updates['location'] = $catalogEntry['location'];
                    $updates['type'] = $catalogEntry['is_nvr'] ? DeviceType::Nvr : DeviceType::Camera;
                }
                $device->update($updates);
                $camUpdates = [
                    'status' => count($macMap) > 1 ? CameraStatus::Unstable : CameraStatus::Online,
                    'last_ok_at' => $now,
                ];
                if ($catalogEntry !== null && $catalogEntry['section'] !== '' && ! $catalogEntry['is_nvr']) {
                    $camUpdates['location_group'] = $catalogEntry['section'];
                }
                $device->camera?->update($camUpdates);
            }
        }

        // 4. Mark any registered device that did NOT respond in this live sweep as Offline in real-time
        if (! empty($discoveredIpMac)) {
            $activeIps = array_keys($discoveredIpMac);
            $offlineDevices = Device::query()
                ->with('camera')
                ->whereNotIn('ip', $activeIps)
                ->where('status', '!=', DeviceStatus::Offline)
                ->get();

            foreach ($offlineDevices as $offDevice) {
                if (str_ends_with($offDevice->ip, '.1') || str_ends_with($offDevice->ip, '.199')) {
                    continue;
                }
                $offDevice->update([
                    'status' => DeviceStatus::Offline,
                    'latency_ms' => null,
                ]);
                $offDevice->camera?->update([
                    'status' => CameraStatus::Offline,
                ]);
            }
        }

        return $count;
    }

    /**
     * @return array<string, array{ip: string, section: string, name: string, location: string, password: string|null, note: string|null, is_nvr: bool}>
     */
    private function loadIpCatalog(): array
    {
        $path = database_path('data/ip_device_catalog.json');
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function detectCameraBrand(string $ip, string $mac): string
    {
        $oui = strtolower(substr($mac, 0, 8));
        $hikvisionOuis = [
            '04:ee:cd', '08:54:11', '08:a1:89', '10:12:fb', '18:68:cb', '24:0f:9b',
            '24:28:fd', '24:32:ae', '28:57:be', '40:ac:bf', '44:19:b6', '44:47:cc',
            '4c:bd:8f', '54:c4:15', '58:03:fb', '64:db:8b', '68:6d:bc', '80:48:9f',
            '84:9a:40', '94:e1:ac', '98:8b:0a', 'a4:14:37', 'a4:29:02', 'a4:a4:59',
            'a4:d5:c2', 'ac:cb:51', 'b4:a3:82', 'bc:5e:33', 'bc:9b:5e', 'bc:ad:28',
            'bc:ba:c2', 'c0:51:7e', 'c0:56:e3', 'c4:2f:90', 'c8:6c:1e', 'd4:e8:53',
            'dc:07:f8', 'dc:d2:6a', 'e0:ba:ad', 'e0:ca:3c', 'e0:df:13', 'e4:d5:8b',
            'e8:a0:ed', 'ec:a9:71', 'f8:4d:fc',
        ];

        if (in_array($oui, $hikvisionOuis, true)) {
            return 'hikvision';
        }

        $socket = @fsockopen($ip, 554, $errno, $errstr, 0.25);
        if (is_resource($socket)) {
            stream_set_timeout($socket, 0, 250_000);
            @fwrite($socket, "DESCRIBE rtsp://{$ip}:554/Streaming/Channels/101 RTSP/1.0\r\nCSeq: 1\r\nAccept: application/sdp\r\n\r\n");
            $resp = (string) @fread($socket, 512);
            @fclose($socket);

            if (str_contains($resp, 'IP Camera(') || preg_match('/realm="[0-9a-f]{24}"/i', $resp) === 1) {
                return 'hikvision';
            }
        }

        return 'onvif_ipc';
    }

    private function syncLiveRoutes(Router $router): void
    {
        $routes = $this->client->routes();
        if (empty($routes)) {
            return;
        }

        $segmentIds = Segment::query()->pluck('id')->all();

        foreach ($routes as $item) {
            $dst = (string) ($item['dst-address'] ?? '');
            $gw = (string) ($item['gateway'] ?? '');
            if ($dst === '' || $gw === '') {
                continue;
            }

            RouterRoute::query()->updateOrCreate(
                [
                    'router_id' => $router->id,
                    'dst_address' => $dst,
                    'gateway' => $gw,
                    'routing_table' => (string) ($item['routing-table'] ?? 'main'),
                ],
                [
                    'distance' => (int) ($item['distance'] ?? 1),
                    'scope' => isset($item['scope']) ? (int) $item['scope'] : null,
                    'is_active' => ($item['active'] ?? 'true') === 'true',
                    'is_dynamic' => ($item['dynamic'] ?? 'false') === 'true',
                    'is_present' => true,
                    'comment' => isset($item['comment']) ? (string) $item['comment'] : 'Route aktif MikroTik-netmap',
                    'segment_ids' => $segmentIds,
                ],
            );
        }
    }
}
