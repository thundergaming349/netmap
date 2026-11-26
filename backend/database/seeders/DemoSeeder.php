<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CameraStatus;
use App\Enums\ConflictStatus;
use App\Enums\ConflictType;
use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Enums\Severity;
use App\Models\ArpObservation;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Camera;
use App\Models\ConflictEvent;
use App\Models\ConflictEventLog;
use App\Models\Device;
use App\Models\DhcpLease;
use App\Models\IpReservation;
use App\Models\MacBlock;
use App\Models\NetworkSwitch;
use App\Models\Nvr;
use App\Models\Router;
use App\Models\RouterRoute;
use App\Models\Segment;
use App\Models\Uplink;
use Illuminate\Database\Seeder;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toImmutable();

        $router = Router::query()->updateOrCreate(
            ['host' => '10.10.99.1'],
            [
                'name' => 'MikroTik-Core',
                'api_port' => 443,
                'use_ssl' => true,
                'webhook_token' => 'nm_whk_99a8b7c6d5e4f3',
                'version' => '7.16.1 (stable)',
                'uptime' => '24d 11:08:42',
                'cpu_load' => 9,
                'last_synced_at' => $now->subSeconds(12),
                'last_error' => null,
            ],
        );

        Uplink::query()->updateOrCreate(
            ['name' => 'ISP-A'],
            [
                'router_id' => $router->id,
                'provider' => 'Biznet Dedicated 200 Mbps',
                'interface' => 'ether1-isp-a',
                'gateway' => '103.142.18.1',
                'check_target' => '8.8.8.8',
                'distance' => 1,
                'status' => 'up',
                'latency_ms' => 11,
                'rx_mbps' => 84,
                'tx_mbps' => 28,
                'last_changed_at' => $now->subDays(14),
            ],
        );

        Uplink::query()->updateOrCreate(
            ['name' => 'ISP-B'],
            [
                'router_id' => $router->id,
                'provider' => 'IndiHome Corporate 100 Mbps',
                'interface' => 'ether2-isp-b',
                'gateway' => '36.88.10.1',
                'check_target' => '1.1.1.1',
                'distance' => 2,
                'status' => 'up',
                'latency_ms' => 19,
                'rx_mbps' => 32,
                'tx_mbps' => 18,
                'last_changed_at' => $now->subDays(7),
            ],
        );

        $office = Segment::query()->updateOrCreate(
            ['slug' => 'kantor'],
            [
                'router_id' => $router->id,
                'name' => 'Kantor',
                'vlan_id' => 10,
                'subnet' => '192.168.10.0/24',
                'gateway' => '192.168.10.1',
                'interface' => 'ether3 · vlan10',
                'pool_start' => '192.168.10.100',
                'pool_end' => '192.168.10.200',
                'static_start' => 2,
                'static_end' => 99,
                'egress' => 'ISP-A (failover ISP-B)',
                'color' => '#4CC2FF',
                'is_isolated' => false,
                'bandwidth_limit' => null,
                'notes' => 'Pool DHCP .100–.200, static .2–.99 (PC staf, printer, server lokal)',
            ],
        );

        $cctv = Segment::query()->updateOrCreate(
            ['slug' => 'cctv'],
            [
                'router_id' => $router->id,
                'name' => 'CCTV',
                'vlan_id' => 20,
                'subnet' => '192.168.20.0/24',
                'gateway' => '192.168.20.1',
                'interface' => 'ether4 · vlan20',
                'pool_start' => null,
                'pool_end' => null,
                'static_start' => 50,
                'static_end' => 99,
                'egress' => 'ISP-B (remote view)',
                'color' => '#FF9A4D',
                'is_isolated' => true,
                'bandwidth_limit' => null,
                'notes' => 'Semua kamera & NVR wajib static, terisolasi dari jalur lain',
            ],
        );

        $guest = Segment::query()->updateOrCreate(
            ['slug' => 'wifi-tamu'],
            [
                'router_id' => $router->id,
                'name' => 'WiFi Tamu',
                'vlan_id' => 30,
                'subnet' => '192.168.30.0/24',
                'gateway' => '192.168.30.1',
                'interface' => 'ether5 · vlan30',
                'pool_start' => '192.168.30.50',
                'pool_end' => '192.168.30.250',
                'static_start' => 2,
                'static_end' => 49,
                'egress' => 'ISP-B, limit 20 Mbps',
                'color' => '#B49BFF',
                'is_isolated' => true,
                'bandwidth_limit' => '20 Mbps',
                'notes' => 'Pool DHCP .50–.250, akses internet saja (client isolation aktif)',
            ],
        );

        $mgmt = Segment::query()->updateOrCreate(
            ['slug' => 'manajemen'],
            [
                'router_id' => $router->id,
                'name' => 'Manajemen',
                'vlan_id' => 99,
                'subnet' => '10.10.99.0/24',
                'gateway' => '10.10.99.1',
                'interface' => 'bridge-trunk · vlan99',
                'pool_start' => null,
                'pool_end' => null,
                'static_start' => 2,
                'static_end' => 99,
                'egress' => 'Lokal saja',
                'color' => '#5FD39A',
                'is_isolated' => true,
                'bandwidth_limit' => null,
                'notes' => 'Switch managed, Access Point, dan mini PC NetMap (.5)',
            ],
        );

        // Switches
        $swOffice1 = NetworkSwitch::query()->updateOrCreate(
            ['name' => 'SW-Kantor-01'],
            [
                'segment_id' => $office->id,
                'ip' => '10.10.99.11',
                'mac' => '74:4d:28:10:01:01',
                'uplink_port' => 'sfp1',
                'total_ports' => 24,
                'is_managed' => true,
                'location' => 'Lantai 1 · Ruang Server',
            ],
        );

        $swOffice2 = NetworkSwitch::query()->updateOrCreate(
            ['name' => 'SW-Kantor-02'],
            [
                'segment_id' => $office->id,
                'ip' => '10.10.99.12',
                'mac' => '74:4d:28:10:01:02',
                'uplink_port' => 'port 24',
                'total_ports' => 24,
                'is_managed' => true,
                'location' => 'Lantai 2 · R. Operasional',
            ],
        );

        $swCctvCore = NetworkSwitch::query()->updateOrCreate(
            ['name' => 'SW-CCTV-Core'],
            [
                'segment_id' => $cctv->id,
                'ip' => '10.10.99.20',
                'mac' => '74:4d:28:20:00:01',
                'uplink_port' => 'ether4 (MikroTik)',
                'total_ports' => 16,
                'is_managed' => true,
                'location' => 'Ruang Server',
            ],
        );

        $swCctv1 = NetworkSwitch::query()->updateOrCreate(
            ['name' => 'SW-CCTV-01'],
            [
                'segment_id' => $cctv->id,
                'ip' => '10.10.99.21',
                'mac' => '74:4d:28:20:01:01',
                'uplink_port' => 'port 16 → SW-CCTV-Core',
                'total_ports' => 16,
                'is_managed' => true,
                'location' => 'Gedung A · Lobi',
            ],
        );

        $swCctv2 = NetworkSwitch::query()->updateOrCreate(
            ['name' => 'SW-CCTV-02'],
            [
                'segment_id' => $cctv->id,
                'ip' => '10.10.99.22',
                'mac' => '74:4d:28:20:02:01',
                'uplink_port' => 'port 16 → SW-CCTV-Core',
                'total_ports' => 16,
                'is_managed' => true,
                'location' => 'Gudang & Area Parkir',
            ],
        );

        $swCctv3 = NetworkSwitch::query()->updateOrCreate(
            ['name' => 'SW-CCTV-03'],
            [
                'segment_id' => $cctv->id,
                'ip' => '10.10.99.23',
                'mac' => '74:4d:28:20:03:01',
                'uplink_port' => 'port 8 → SW-CCTV-Core',
                'total_ports' => 8,
                'is_managed' => true,
                'location' => 'Gedung C · Produksi',
            ],
        );

        $swMgmt = NetworkSwitch::query()->updateOrCreate(
            ['name' => 'SW-Mgmt-Core'],
            [
                'segment_id' => $mgmt->id,
                'ip' => '10.10.99.2',
                'mac' => '74:4d:28:99:00:02',
                'uplink_port' => 'bridge-trunk',
                'total_ports' => 24,
                'is_managed' => true,
                'location' => 'Rak Utama Ruang Server',
            ],
        );

        // Reservations
        IpReservation::query()->updateOrCreate(
            ['segment_id' => $cctv->id, 'start_octet' => 40, 'end_octet' => 49],
            [
                'ip_start' => '192.168.20.40',
                'ip_end' => '192.168.20.49',
                'label' => 'Kamera Gedung B (Tahap Ekspansi)',
                'description' => 'Dicadangkan untuk 10 titik kamera baru Gedung B bulan depan.',
            ],
        );

        IpReservation::query()->updateOrCreate(
            ['segment_id' => $office->id, 'start_octet' => 20, 'end_octet' => 29],
            [
                'ip_start' => '192.168.10.20',
                'ip_end' => '192.168.10.29',
                'label' => 'Printer & Scanner Lantai 1–3',
                'description' => 'Blok khusus perangkat cetak jaringan kantor.',
            ],
        );

        IpReservation::query()->updateOrCreate(
            ['segment_id' => $cctv->id, 'start_octet' => 250, 'end_octet' => 250],
            [
                'ip_start' => '192.168.20.250',
                'ip_end' => '192.168.20.250',
                'label' => 'Sub-interface Scanner Mini PC NetMap',
                'description' => 'Dipakai eth0.20 mini PC untuk arping & stream RTSP.',
            ],
        );

        // NVR Devices & NVRs
        $nvrDev1 = Device::query()->updateOrCreate(
            ['segment_id' => $cctv->id, 'ip' => '192.168.20.2'],
            [
                'network_switch_id' => $swCctvCore->id,
                'name' => 'NVR-01-Utama',
                'type' => DeviceType::Nvr,
                'status' => DeviceStatus::Online,
                'mac' => 'bc:32:5f:00:02:01',
                'location' => 'Ruang Server Rak A',
                'port' => 'port 1',
                'note' => 'Hikvision DS-7732NI-I4 (32 Channel)',
                'monitor_ping' => true,
                'is_static_lease' => true,
                'is_arp_pinned' => true,
                'last_seen_at' => $now,
                'latency_ms' => 2,
            ],
        );

        $nvrDev2 = Device::query()->updateOrCreate(
            ['segment_id' => $cctv->id, 'ip' => '192.168.20.3'],
            [
                'network_switch_id' => $swCctvCore->id,
                'name' => 'NVR-02-Gudang',
                'type' => DeviceType::Nvr,
                'status' => DeviceStatus::Online,
                'mac' => 'bc:32:5f:00:02:02',
                'location' => 'Ruang Server Rak A',
                'port' => 'port 2',
                'note' => 'Dahua NVR5216-16P (16 Channel)',
                'monitor_ping' => true,
                'is_static_lease' => true,
                'is_arp_pinned' => true,
                'last_seen_at' => $now,
                'latency_ms' => 2,
            ],
        );

        $nvr1 = Nvr::query()->updateOrCreate(
            ['ip' => '192.168.20.2'],
            [
                'device_id' => $nvrDev1->id,
                'name' => 'NVR-01 (Gedung A & Gudang)',
                'brand' => 'hikvision',
                'web_url' => 'http://192.168.20.2',
                'channel_count' => 32,
                'capacity_bytes' => 16_000_000_000_000,
                'used_bytes' => 11_520_000_000_000,
                'retention_days' => 21,
                'status' => 'online',
                'last_ok_at' => $now,
            ],
        );

        $nvr2 = Nvr::query()->updateOrCreate(
            ['ip' => '192.168.20.3'],
            [
                'device_id' => $nvrDev2->id,
                'name' => 'NVR-02 (Parkir & Gedung C)',
                'brand' => 'dahua',
                'web_url' => 'http://192.168.20.3',
                'channel_count' => 16,
                'capacity_bytes' => 8_000_000_000_000,
                'used_bytes' => 5_100_000_000_000,
                'retention_days' => 18,
                'status' => 'online',
                'last_ok_at' => $now,
            ],
        );

        // Cameras (16 cameras across Gedung A, Parkir, Gudang, Gedung C)
        $cameraSpecs = [
            ['octet' => 11, 'name' => 'Cam-GdgA-Lobi-01', 'group' => 'Gedung A', 'sw' => $swCctv1, 'port' => 'port 1', 'nvr' => $nvr1, 'ch' => 1, 'status' => CameraStatus::Online],
            ['octet' => 12, 'name' => 'Cam-GdgA-Resepsionis', 'group' => 'Gedung A', 'sw' => $swCctv1, 'port' => 'port 2', 'nvr' => $nvr1, 'ch' => 2, 'status' => CameraStatus::Online],
            ['octet' => 13, 'name' => 'Cam-GdgA-Koridor-Lt1', 'group' => 'Gedung A', 'sw' => $swCctv1, 'port' => 'port 3', 'nvr' => $nvr1, 'ch' => 3, 'status' => CameraStatus::Online],
            ['octet' => 14, 'name' => 'Cam-GdgA-Koridor-Lt2', 'group' => 'Gedung A', 'sw' => $swCctv1, 'port' => 'port 4', 'nvr' => $nvr1, 'ch' => 4, 'status' => CameraStatus::Online],
            ['octet' => 15, 'name' => 'Cam-GdgA-R.Server', 'group' => 'Gedung A', 'sw' => $swCctv1, 'port' => 'port 5', 'nvr' => $nvr1, 'ch' => 5, 'status' => CameraStatus::Online],
            ['octet' => 26, 'name' => 'Cam-Parkir-Gerbang', 'group' => 'Parkir', 'sw' => $swCctv2, 'port' => 'port 1', 'nvr' => $nvr2, 'ch' => 1, 'status' => CameraStatus::Online],
            ['octet' => 27, 'name' => 'Cam-Parkir-Utara', 'group' => 'Parkir', 'sw' => $swCctv2, 'port' => 'port 2', 'nvr' => $nvr2, 'ch' => 2, 'status' => CameraStatus::Offline],
            ['octet' => 28, 'name' => 'Cam-Parkir-Motor', 'group' => 'Parkir', 'sw' => $swCctv2, 'port' => 'port 3', 'nvr' => $nvr2, 'ch' => 3, 'status' => CameraStatus::Online],
            ['octet' => 29, 'name' => 'Cam-Gudang-01-Bongkar', 'group' => 'Gudang', 'sw' => $swCctv2, 'port' => 'port 5', 'nvr' => $nvr1, 'ch' => 6, 'status' => CameraStatus::Online],
            ['octet' => 30, 'name' => 'Cam-Gudang-02-RakUtama', 'group' => 'Gudang', 'sw' => $swCctv2, 'port' => 'port 6', 'nvr' => $nvr1, 'ch' => 7, 'status' => CameraStatus::Online],
            ['octet' => 31, 'name' => 'Cam-Gudang-03-PintuBrs', 'group' => 'Gudang', 'sw' => $swCctv2, 'port' => 'port 7', 'nvr' => $nvr1, 'ch' => 8, 'status' => CameraStatus::Unstable],
            ['octet' => 32, 'name' => 'Cam-Gudang-04-Packing', 'group' => 'Gudang', 'sw' => $swCctv2, 'port' => 'port 8', 'nvr' => $nvr1, 'ch' => 9, 'status' => CameraStatus::Online],
            ['octet' => 55, 'name' => 'Cam-GdgC-Produksi-01', 'group' => 'Gedung C', 'sw' => $swCctv3, 'port' => 'port 1', 'nvr' => $nvr2, 'ch' => 4, 'status' => CameraStatus::Online],
            ['octet' => 56, 'name' => 'Cam-GdgC-Produksi-02', 'group' => 'Gedung C', 'sw' => $swCctv3, 'port' => 'port 2', 'nvr' => $nvr2, 'ch' => 5, 'status' => CameraStatus::Online],
            ['octet' => 57, 'name' => 'Cam-GdgC-QC-Area', 'group' => 'Gedung C', 'sw' => $swCctv3, 'port' => 'port 3', 'nvr' => $nvr2, 'ch' => 6, 'status' => CameraStatus::Online],
            ['octet' => 58, 'name' => 'Cam-GdgC-LoadingDock', 'group' => 'Gedung C', 'sw' => $swCctv3, 'port' => 'port 4', 'nvr' => $nvr2, 'ch' => 7, 'status' => CameraStatus::Online],
        ];

        foreach ($cameraSpecs as $spec) {
            $octHex = str_pad(dechex($spec['octet']), 2, '0', STR_PAD_LEFT);
            $ip = "192.168.20.{$spec['octet']}";
            $mac = "bc:32:5f:1a:0c:{$octHex}";

            $devStatus = match ($spec['status']) {
                CameraStatus::Offline => DeviceStatus::Offline,
                CameraStatus::Unstable => DeviceStatus::Conflict,
                CameraStatus::Online => DeviceStatus::Online,
            };

            $camDevice = Device::query()->updateOrCreate(
                ['segment_id' => $cctv->id, 'ip' => $ip],
                [
                    'network_switch_id' => $spec['sw']->id,
                    'name' => $spec['name'],
                    'type' => DeviceType::Camera,
                    'status' => $devStatus,
                    'mac' => $mac,
                    'location' => $spec['group'],
                    'port' => $spec['port'],
                    'note' => $spec['octet'] === 31
                        ? 'Bentrok IP dengan laptop teknisi di port 12'
                        : 'Kamera PoE Hikvision 4MP H.265+',
                    'monitor_ping' => true,
                    'is_static_lease' => true,
                    'is_arp_pinned' => $spec['octet'] !== 31,
                    'last_seen_at' => $spec['status'] === CameraStatus::Offline ? $now->subMinutes(28) : $now,
                    'latency_ms' => $spec['status'] === CameraStatus::Offline ? null : ($spec['status'] === CameraStatus::Unstable ? 145 : 3),
                ],
            );

            $streamKey = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $spec['name']));

            Camera::query()->updateOrCreate(
                ['device_id' => $camDevice->id],
                [
                    'nvr_id' => $spec['nvr']->id,
                    'channel' => $spec['ch'],
                    'brand' => $spec['nvr']->brand,
                    'location_group' => $spec['group'],
                    'stream_key' => $streamKey,
                    'stream_main' => "rtsp://{$ip}:554/Streaming/Channels/101",
                    'stream_sub' => "rtsp://{$ip}:554/Streaming/Channels/102",
                    'status' => $spec['status'],
                    'fps' => $spec['status'] === CameraStatus::Unstable ? 12 : 25,
                    'resolution' => '1920x1080',
                    'last_ok_at' => $spec['status'] === CameraStatus::Offline ? $now->subMinutes(28) : $now,
                ],
            );
        }

        // Office & Management Devices
        $otherDevices = [
            ['seg' => $office, 'sw' => $swOffice1, 'ip' => '192.168.10.10', 'mac' => '00:1b:21:8a:10:10', 'name' => 'SRV-ERP-Lokal', 'type' => DeviceType::Server, 'loc' => 'Ruang Server', 'port' => 'port 1', 'static_lease' => true],
            ['seg' => $office, 'sw' => $swOffice1, 'ip' => '192.168.10.11', 'mac' => '00:1b:21:8a:10:11', 'name' => 'NAS-Synology-Backup', 'type' => DeviceType::Server, 'loc' => 'Ruang Server', 'port' => 'port 2', 'static_lease' => true],
            ['seg' => $office, 'sw' => $swOffice1, 'ip' => '192.168.10.21', 'mac' => '3c:2a:f4:11:20:21', 'name' => 'Printer-Resepsionis-Lt1', 'type' => DeviceType::Printer, 'loc' => 'Lantai 1', 'port' => 'port 6', 'static_lease' => true],
            ['seg' => $office, 'sw' => $swOffice2, 'ip' => '192.168.10.22', 'mac' => '3c:2a:f4:11:20:22', 'name' => 'Printer-Keuangan-Lt2', 'type' => DeviceType::Printer, 'loc' => 'Lantai 2', 'port' => 'port 8', 'static_lease' => true],
            ['seg' => $office, 'sw' => $swOffice1, 'ip' => '192.168.10.35', 'mac' => 'd8:bb:c1:40:10:35', 'name' => 'PC-Kasir-Utama', 'type' => DeviceType::Pc, 'loc' => 'Lantai 1', 'port' => 'port 10', 'static_lease' => true],
            ['seg' => $office, 'sw' => $swOffice2, 'ip' => '192.168.10.36', 'mac' => 'd8:bb:c1:40:10:36', 'name' => 'PC-Admin-Gudang', 'type' => DeviceType::Pc, 'loc' => 'Gudang', 'port' => 'port 12', 'static_lease' => true],
            // Static device placed inside DHCP pool (.100-.200) -> triggers STATIC_IN_POOL risk!
            ['seg' => $office, 'sw' => $swOffice2, 'ip' => '192.168.10.120', 'mac' => '3c:2a:f4:99:10:78', 'name' => 'Printer-HRD-Lt2', 'type' => DeviceType::Printer, 'loc' => 'Lantai 2 · HRD', 'port' => 'port 15', 'static_lease' => false],
            // Management segment devices
            ['seg' => $mgmt, 'sw' => $swMgmt, 'ip' => '10.10.99.5', 'mac' => 'e4:5f:01:99:00:05', 'name' => 'MiniPC-NetMap', 'type' => DeviceType::Server, 'loc' => 'Rak Utama Ruang Server', 'port' => 'port 5 (trunk)', 'static_lease' => true],
            ['seg' => $mgmt, 'sw' => $swMgmt, 'ip' => '10.10.99.31', 'mac' => '74:83:c2:50:00:31', 'name' => 'AP-Kantor-Lt1', 'type' => DeviceType::AccessPoint, 'loc' => 'Lantai 1', 'port' => 'port 11', 'static_lease' => true],
            ['seg' => $mgmt, 'sw' => $swMgmt, 'ip' => '10.10.99.32', 'mac' => '74:83:c2:50:00:32', 'name' => 'AP-Kantor-Lt2', 'type' => DeviceType::AccessPoint, 'loc' => 'Lantai 2', 'port' => 'port 12', 'static_lease' => true],
            ['seg' => $mgmt, 'sw' => $swMgmt, 'ip' => '10.10.99.33', 'mac' => '74:83:c2:50:00:33', 'name' => 'AP-Lobby-Tamu', 'type' => DeviceType::AccessPoint, 'loc' => 'Lobi Utama', 'port' => 'port 13', 'static_lease' => true],
        ];

        foreach ($otherDevices as $dev) {
            Device::query()->updateOrCreate(
                ['segment_id' => $dev['seg']->id, 'ip' => $dev['ip']],
                [
                    'network_switch_id' => $dev['sw']->id,
                    'name' => $dev['name'],
                    'type' => $dev['type'],
                    'status' => $dev['static_lease'] ? DeviceStatus::Online : DeviceStatus::Risk,
                    'mac' => $dev['mac'],
                    'location' => $dev['loc'],
                    'port' => $dev['port'],
                    'monitor_ping' => true,
                    'is_static_lease' => $dev['static_lease'],
                    'is_arp_pinned' => $dev['static_lease'],
                    'last_seen_at' => $now,
                    'latency_ms' => 2,
                ],
            );
        }

        // Dynamic DHCP Leases on Kantor & WiFi Tamu
        $dhcpSamples = [
            ['seg' => $office, 'ip' => '192.168.10.101', 'mac' => 'a4:83:e7:10:01:01', 'host' => 'MacBook-Fajar'],
            ['seg' => $office, 'ip' => '192.168.10.102', 'mac' => 'a4:83:e7:10:01:02', 'host' => 'ThinkPad-Rina'],
            ['seg' => $office, 'ip' => '192.168.10.103', 'mac' => 'a4:83:e7:10:01:03', 'host' => 'Laptop-Budi-Sales'],
            ['seg' => $office, 'ip' => '192.168.10.104', 'mac' => 'a4:83:e7:10:01:04', 'host' => 'PC-Desain-01'],
            ['seg' => $office, 'ip' => '192.168.10.105', 'mac' => 'a4:83:e7:10:01:05', 'host' => 'VivoBook-Sari'],
            ['seg' => $guest, 'ip' => '192.168.30.51', 'mac' => 'ca:12:9b:30:00:51', 'host' => 'iPhone-Tamu'],
            ['seg' => $guest, 'ip' => '192.168.30.52', 'mac' => 'ca:12:9b:30:00:52', 'host' => 'Galaxy-S24-Visitor'],
            ['seg' => $guest, 'ip' => '192.168.30.53', 'mac' => 'ca:12:9b:30:00:53', 'host' => 'iPad-Meeting'],
        ];

        foreach ($dhcpSamples as $lease) {
            DhcpLease::query()->updateOrCreate(
                ['segment_id' => $lease['seg']->id, 'ip' => $lease['ip'], 'mac' => $lease['mac']],
                [
                    'host_name' => $lease['host'],
                    'server' => 'dhcp-'.$lease['seg']->slug,
                    'status' => 'bound',
                    'is_dynamic' => true,
                    'is_present' => true,
                    'last_seen_at' => $now,
                ],
            );
        }

        // ARP Observations for all registered devices + duplicate MAC on 192.168.20.31 + reservation misuse on 192.168.20.42
        foreach (Device::query()->with('segment')->whereNotNull('mac')->get() as $device) {
            ArpObservation::query()->updateOrCreate(
                ['segment_id' => $device->segment_id, 'ip' => $device->ip, 'mac' => (string) $device->mac],
                [
                    'interface' => $device->segment !== null ? $device->segment->interface : 'vlan',
                    'is_dynamic' => ! $device->is_arp_pinned,
                    'is_complete' => true,
                    'first_seen_at' => $now->subDays(10),
                    'last_seen_at' => $device->status === DeviceStatus::Offline ? $now->subMinutes(28) : $now,
                ],
            );
        }

        // Duplicate MAC on 192.168.20.31 (Cam-Gudang-03) -> triggers DUP_IP!
        ArpObservation::query()->updateOrCreate(
            ['segment_id' => $cctv->id, 'ip' => '192.168.20.31', 'mac' => 'f0:de:f1:99:88:77'],
            [
                'interface' => $cctv->interface,
                'is_dynamic' => true,
                'is_complete' => true,
                'first_seen_at' => $now->subMinutes(12),
                'last_seen_at' => $now->subMinutes(1),
            ],
        );

        // Unauthorized device inside reserved range .40-.49 (192.168.20.42) -> triggers RES_MISUSE!
        ArpObservation::query()->updateOrCreate(
            ['segment_id' => $cctv->id, 'ip' => '192.168.20.42', 'mac' => '08:00:27:42:aa:bb'],
            [
                'interface' => $cctv->interface,
                'is_dynamic' => true,
                'is_complete' => true,
                'first_seen_at' => $now->subMinutes(18),
                'last_seen_at' => $now->subMinutes(2),
            ],
        );

        // Router Routes (/ip route snapshot)
        $routes = [
            ['dst' => '0.0.0.0/0', 'gw' => '103.142.18.1 (ether1-isp-a)', 'table' => 'main', 'dist' => 1, 'active' => true, 'dyn' => false, 'comment' => 'Default route utama via ISP-A Biznet', 'segs' => [$office->id]],
            ['dst' => '0.0.0.0/0', 'gw' => '36.88.10.1 (ether2-isp-b)', 'table' => 'main', 'dist' => 2, 'active' => false, 'dyn' => false, 'comment' => 'Failover cadangan via ISP-B IndiHome', 'segs' => [$office->id]],
            ['dst' => '0.0.0.0/0', 'gw' => '36.88.10.1 (ether2-isp-b)', 'table' => 'to-isp-b', 'dist' => 1, 'active' => true, 'dyn' => false, 'comment' => 'Policy route CCTV & WiFi Tamu keluar lewat ISP-B', 'segs' => [$cctv->id, $guest->id]],
            ['dst' => '192.168.10.0/24', 'gw' => 'ether3 · vlan10', 'table' => 'main', 'dist' => 0, 'active' => true, 'dyn' => true, 'comment' => 'Connected route Jalur Kantor', 'segs' => [$office->id]],
            ['dst' => '192.168.20.0/24', 'gw' => 'ether4 · vlan20', 'table' => 'main', 'dist' => 0, 'active' => true, 'dyn' => true, 'comment' => 'Connected route Jalur CCTV (terisolasi)', 'segs' => [$cctv->id]],
            ['dst' => '192.168.30.0/24', 'gw' => 'ether5 · vlan30', 'table' => 'main', 'dist' => 0, 'active' => true, 'dyn' => true, 'comment' => 'Connected route Jalur WiFi Tamu (limit 20 Mbps)', 'segs' => [$guest->id]],
            ['dst' => '10.10.99.0/24', 'gw' => 'bridge-trunk · vlan99', 'table' => 'main', 'dist' => 0, 'active' => true, 'dyn' => true, 'comment' => 'Connected route Jalur Manajemen', 'segs' => [$mgmt->id]],
        ];

        foreach ($routes as $r) {
            RouterRoute::query()->updateOrCreate(
                ['dst_address' => $r['dst'], 'gateway' => $r['gw'], 'routing_table' => $r['table']],
                [
                    'router_id' => $router->id,
                    'distance' => $r['dist'],
                    'scope' => $r['dist'] === 0 ? 10 : 30,
                    'is_active' => $r['active'],
                    'is_dynamic' => $r['dyn'],
                    'is_present' => true,
                    'comment' => $r['comment'],
                    'segment_ids' => $r['segs'],
                ],
            );
        }

        // Seed active & resolved conflict events
        $cam31 = Device::query()->where('ip', '192.168.20.31')->first();
        $dupEvent = ConflictEvent::query()->updateOrCreate(
            ['type' => ConflictType::DupIp->value, 'segment_id' => $cctv->id, 'ip' => '192.168.20.31', 'status' => ConflictStatus::Active->value],
            [
                'severity' => Severity::Conflict,
                'macs' => ['bc:32:5f:1a:0c:1f', 'f0:de:f1:99:88:77'],
                'title' => 'IP 192.168.20.31 dipakai 2 perangkat (Cam-Gudang-03-PintuBrs)',
                'summary' => '192.168.20.31 terdaftar untuk Cam-Gudang-03-PintuBrs (BC:32:5F:1A:0C:1F di SW-CCTV-02 port 7), tetapi MAC F0:DE:F1:99:88:77 juga merespons di SW-CCTV-02 port 12.',
                'suggestion' => 'Pindahkan perangkat tak dikenal ke 192.168.20.50 atau blokir MAC F0:DE:F1:99:88:77.',
                'source' => 'arp_history + arping -D',
                'device_id' => $cam31?->id,
                'opened_at' => $now->subMinutes(12),
                'last_detected_at' => $now->subMinutes(1),
            ],
        );

        if ($dupEvent->logs()->count() === 0) {
            ConflictEventLog::create([
                'conflict_event_id' => $dupEvent->id,
                'logged_at' => $now->subMinutes(12),
                'message' => 'ARP MikroTik mencatat perubahan MAC pada 192.168.20.31: BC:32:5F:1A:0C:1F → F0:DE:F1:99:88:77.',
            ]);
            ConflictEventLog::create([
                'conflict_event_id' => $dupEvent->id,
                'logged_at' => $now->subMinutes(8),
                'message' => 'Probe arping -D dari eth0.20 menerima 2 balasan ARP untuk 192.168.20.31 (1.1 ms & 2.4 ms).',
            ]);
            ConflictEventLog::create([
                'conflict_event_id' => $dupEvent->id,
                'logged_at' => $now->subMinutes(2),
                'message' => 'Stream Cam-Gudang-03-PintuBrs berubah status menjadi TAK STABIL akibat paket bertabrakan.',
            ]);
        }

        $resMisuseEvent = ConflictEvent::query()->updateOrCreate(
            ['type' => ConflictType::ResMisuse->value, 'segment_id' => $cctv->id, 'ip' => '192.168.20.42', 'status' => ConflictStatus::Active->value],
            [
                'severity' => Severity::Conflict,
                'macs' => ['08:00:27:42:aa:bb'],
                'title' => 'IP reservasi 192.168.20.42 dipakai perangkat tanpa izin',
                'summary' => 'Alamat 192.168.20.42 dicadangkan untuk "Kamera Gedung B (Tahap Ekspansi)" (.40–.49), namun sedang dipakai oleh MAC 08:00:27:42:AA:BB.',
                'suggestion' => 'Pindahkan perangkat tersebut ke IP kosong 192.168.20.50 di luar blok reservasi.',
                'source' => 'arp + reservation',
                'device_id' => null,
                'opened_at' => $now->subMinutes(18),
                'last_detected_at' => $now->subMinutes(2),
            ],
        );

        if ($resMisuseEvent->logs()->count() === 0) {
            ConflictEventLog::create([
                'conflict_event_id' => $resMisuseEvent->id,
                'logged_at' => $now->subMinutes(18),
                'message' => 'MAC 08:00:27:42:AA:BB muncul di tabel ARP vlan20 menggunakan IP 192.168.20.42 (range reservasi .40–.49).',
            ]);
        }

        $printerHrd = Device::query()->where('ip', '192.168.10.120')->first();
        $staticPoolEvent = ConflictEvent::query()->updateOrCreate(
            ['type' => ConflictType::StaticInPool->value, 'segment_id' => $office->id, 'ip' => '192.168.10.120', 'status' => ConflictStatus::Active->value],
            [
                'severity' => Severity::Risk,
                'macs' => ['3c:2a:f4:99:10:78'],
                'title' => 'Printer-HRD-Lt2 (192.168.10.120) dipasang static di dalam pool DHCP',
                'summary' => '192.168.10.120 berada di dalam range pool DHCP Kantor (.100–.200) tanpa lease static. Sewaktu-waktu DHCP server bisa membagikan IP ini ke laptop karyawan.',
                'suggestion' => 'Buat DHCP lease static untuk MAC 3C:2A:F4:99:10:78 atau pindahkan IP printer ke 192.168.10.23.',
                'source' => 'arp + lease + pool',
                'device_id' => $printerHrd?->id,
                'opened_at' => $now->subHours(2),
                'last_detected_at' => $now->subMinutes(1),
            ],
        );

        if ($staticPoolEvent->logs()->count() === 0) {
            ConflictEventLog::create([
                'conflict_event_id' => $staticPoolEvent->id,
                'logged_at' => $now->subHours(2),
                'message' => 'Perangkat aktif di 192.168.10.120 tidak memiliki entri di /ip/dhcp-server/lease padahal berada di dalam pool .100–.200.',
            ]);
        }

        $cam27 = Device::query()->where('ip', '192.168.20.27')->first();
        $camOfflineEvent = ConflictEvent::query()->updateOrCreate(
            ['type' => ConflictType::CamOffline->value, 'segment_id' => $cctv->id, 'ip' => '192.168.20.27', 'status' => ConflictStatus::Active->value],
            [
                'severity' => Severity::Risk,
                'macs' => ['bc:32:5f:1a:0c:1b'],
                'title' => 'Kamera Cam-Parkir-Utara (192.168.20.27) offline > 25 menit',
                'summary' => 'Kamera di SW-CCTV-02 port 2 tidak merespons fping dan stream RTSP terputus sejak 28 menit lalu.',
                'suggestion' => 'Restart daya PoE pada SW-CCTV-02 port 2 atau periksa konektor RJ45 di tiang parkir utara.',
                'source' => 'fping + go2rtc',
                'device_id' => $cam27?->id,
                'opened_at' => $now->subMinutes(23),
                'last_detected_at' => $now->subMinutes(1),
            ],
        );

        if ($camOfflineEvent->logs()->count() === 0) {
            ConflictEventLog::create([
                'conflict_event_id' => $camOfflineEvent->id,
                'logged_at' => $now->subMinutes(28),
                'message' => 'Ping fping ke 192.168.20.27 gagal 10x berturut-turut (100% packet loss).',
            ]);
            ConflictEventLog::create([
                'conflict_event_id' => $camOfflineEvent->id,
                'logged_at' => $now->subMinutes(23),
                'message' => 'Notifikasi Telegram dikirim ke grup Tim IT & Keamanan.',
            ]);
        }

        // Historical resolved conflict event
        $resolvedEvent = ConflictEvent::query()->updateOrCreate(
            ['type' => ConflictType::DupIp->value, 'segment_id' => $office->id, 'ip' => '192.168.10.21', 'status' => ConflictStatus::Resolved->value],
            [
                'severity' => Severity::Conflict,
                'macs' => ['3c:2a:f4:11:20:21', '90:e2:ba:01:99:11'],
                'title' => 'Bentrok IP pada Printer-Resepsionis-Lt1 (192.168.10.21)',
                'summary' => 'PC baru sempat diset manual ke 192.168.10.21 dan menyebabkan printer resepsionis tidak bisa mencetak.',
                'suggestion' => 'Pindahkan PC ke DHCP otomatis.',
                'source' => 'arp_history',
                'opened_at' => $now->subDays(1)->subHours(3),
                'last_detected_at' => $now->subDays(1)->subHours(2),
                'resolved_at' => $now->subDays(1)->subHours(2),
                'resolution_note' => 'PC karyawan sudah diubah kembali ke DHCP otomatis.',
            ],
        );

        if ($resolvedEvent->logs()->count() === 0) {
            ConflictEventLog::create([
                'conflict_event_id' => $resolvedEvent->id,
                'logged_at' => $now->subDays(1)->subHours(3),
                'message' => 'Terdeteksi 2 MAC berbeda pada 192.168.10.21.',
            ]);
            ConflictEventLog::create([
                'conflict_event_id' => $resolvedEvent->id,
                'logged_at' => $now->subDays(1)->subHours(2),
                'message' => 'Ditandai selesai: PC karyawan sudah diubah kembali ke DHCP otomatis.',
            ]);
        }

        MacBlock::query()->updateOrCreate(
            ['mac' => 'dc:a6:32:99:ee:ff'],
            [
                'router_id' => $router->id,
                'segment_id' => $cctv->id,
                'ip' => '192.168.20.19',
                'reason' => 'Perangkat tidak dikenal mencoba memakai IP kamera lobi',
                'routeros_id' => '*4F1A',
            ],
        );

        if (Backup::query()->count() === 0) {
            Backup::create([
                'filename' => 'netmap-backup-'.$now->subDay()->format('Y-m-d').'-020000.tar.gz',
                'size_bytes' => 1_794_120,
                'includes_mikrotik_export' => true,
                'status' => 'completed',
                'created_at' => $now->subDay()->setTime(2, 0),
            ]);
            Backup::create([
                'filename' => 'netmap-backup-'.$now->format('Y-m-d').'-020000.tar.gz',
                'size_bytes' => 1_842_600,
                'includes_mikrotik_export' => true,
                'status' => 'completed',
                'created_at' => $now->setTime(2, 0),
            ]);
        }

        if (AuditLog::query()->count() < 4) {
            AuditLog::record(
                action: 'mikrotik.sync',
                target: 'MikroTik-Core (10.10.99.1)',
                after: ['segments' => 4, 'devices' => 27, 'active_conflicts' => 4],
            );
            AuditLog::record(
                action: 'reservation.created',
                target: 'CCTV .40–.49 (Kamera Gedung B)',
                after: ['ip_start' => '192.168.20.40', 'ip_end' => '192.168.20.49'],
            );
            AuditLog::record(
                action: 'mikrotik.mac.block',
                target: 'DC:A6:32:99:EE:FF',
                after: ['reason' => 'Perangkat tidak dikenal di VLAN 20'],
            );
        }
    }
}
