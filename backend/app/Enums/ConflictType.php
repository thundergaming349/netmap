<?php

declare(strict_types=1);

namespace App\Enums;

enum ConflictType: string
{
    case DupIp = 'dup_ip';
    case ResMisuse = 'res_misuse';
    case RegMismatch = 'reg_mismatch';
    case StaticInPool = 'static_in_pool';
    case OutOfSubnet = 'out_of_subnet';
    case MacFlap = 'mac_flap';
    case NewDevice = 'new_device';
    case CamOffline = 'cam_offline';

    public function label(): string
    {
        return match ($this) {
            self::DupIp => 'IP duplikat (dipakai >1 MAC)',
            self::ResMisuse => 'IP reservasi dipakai perangkat lain',
            self::RegMismatch => 'IP terdaftar dipakai MAC berbeda',
            self::StaticInPool => 'IP static di dalam pool DHCP',
            self::OutOfSubnet => 'IP di luar subnet jalur',
            self::MacFlap => 'MAC berpindah port switch',
            self::NewDevice => 'Perangkat baru terdeteksi',
            self::CamOffline => 'Kamera tidak merespons',
        };
    }

    public function severity(): Severity
    {
        return match ($this) {
            self::DupIp, self::ResMisuse, self::RegMismatch => Severity::Conflict,
            self::StaticInPool, self::OutOfSubnet, self::MacFlap, self::CamOffline => Severity::Risk,
            self::NewDevice => Severity::Info,
        };
    }

    public function defaultSuggestion(?string $freeIp = null): string
    {
        $targetIp = $freeIp ?? 'IP kosong di range static';

        return match ($this) {
            self::DupIp => "Pindahkan perangkat yang tidak dikenal ke {$targetIp} atau blokir MAC-nya, lalu kunci perangkat sah dengan DHCP lease static.",
            self::ResMisuse => "Pindahkan perangkat ke {$targetIp} di luar range reservasi.",
            self::RegMismatch => "Periksa perangkat fisik di lokasi. Jika perangkat diganti baru, perbarui alamat MAC di inventaris atau pindahkan perangkat asing ke {$targetIp}.",
            self::StaticInPool => 'Buat DHCP lease static untuk MAC ini supaya IP tidak dibagikan ke perangkat lain, atau pindahkan ke range static di luar pool.',
            self::OutOfSubnet => 'Periksa port switch terkait dan cabut router/DHCP server liar yang membagikan subnet berbeda.',
            self::MacFlap => 'Periksa kabel jaringan dari kemungkinan loop topologi atau perbarui lokasi port di inventaris.',
            self::NewDevice => 'Daftarkan perangkat ke inventaris jika sah, atau blokir MAC jika tidak dikenal.',
            self::CamOffline => 'Restart PoE port pada switch/MikroTik atau periksa koneksi kabel menuju kamera.',
        };
    }
}
