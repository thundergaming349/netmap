<?php

declare(strict_types=1);

namespace App\Services\Conflicts;

use App\Actions\IpMap\SuggestNextFreeIp;
use App\Enums\CameraStatus;
use App\Enums\ConflictStatus;
use App\Enums\ConflictType;
use App\Models\ArpObservation;
use App\Models\Camera;
use App\Models\ConflictEvent;
use App\Models\ConflictEventLog;
use App\Models\Device;
use App\Models\DhcpLease;
use App\Models\IpReservation;
use App\Models\Segment;
use App\Models\Setting;

final class RuleEngine
{
    public function __construct(
        private readonly SuggestNextFreeIp $suggestNextFreeIp,
    ) {}

    /**
     * Evaluate enabled conflict detection rules and return total active conflicts count.
     */
    public function evaluateAll(): int
    {
        $enabledRules = $this->enabledRules();
        $segments = Segment::query()->get();

        foreach ($segments as $segment) {
            if ($enabledRules[ConflictType::DupIp->value] ?? true) {
                $this->evaluateDuplicateIp($segment);
            }

            if ($enabledRules[ConflictType::ResMisuse->value] ?? true) {
                $this->evaluateReservationMisuse($segment);
            }

            if ($enabledRules[ConflictType::RegMismatch->value] ?? true) {
                $this->evaluateRegisteredMismatch($segment);
            }

            if ($enabledRules[ConflictType::StaticInPool->value] ?? true) {
                $this->evaluateStaticInPool($segment);
            }
        }

        if ($enabledRules[ConflictType::CamOffline->value] ?? true) {
            $this->evaluateOfflineCameras();
        }

        // Auto-resolve active events whose condition has cleared for >= 15 minutes.
        ConflictEvent::query()
            ->active()
            ->where('last_detected_at', '<', now()->subMinutes(15))
            ->get()
            ->each(function (ConflictEvent $event): void {
                $event->update([
                    'status' => ConflictStatus::Resolved,
                    'resolved_at' => now(),
                    'resolution_note' => 'Kondisi konflik tidak terdeteksi lagi selama 15 menit (selesai otomatis).',
                ]);
                ConflictEventLog::create([
                    'conflict_event_id' => $event->id,
                    'logged_at' => now(),
                    'message' => 'Ditutup otomatis karena kondisi kembali normal ≥ 15 menit.',
                ]);
            });

        return ConflictEvent::query()->active()->count();
    }

    /**
     * @return array<string, bool>
     */
    public function enabledRules(): array
    {
        $saved = Setting::groupValues('rules');
        $defaults = [];
        foreach (ConflictType::cases() as $type) {
            $defaults[$type->value] = (bool) ($saved[$type->value] ?? true);
        }

        return $defaults;
    }

    private function evaluateDuplicateIp(Segment $segment): void
    {
        $observations = ArpObservation::query()
            ->where('segment_id', $segment->id)
            ->recent(15)
            ->get()
            ->groupBy('ip');

        foreach ($observations as $ip => $items) {
            /** @var list<string> $macs */
            $macs = $items->pluck('mac')->unique()->values()->all();
            if (count($macs) < 2) {
                continue;
            }

            $device = Device::query()->where('segment_id', $segment->id)->where('ip', $ip)->first();
            $freeIp = $this->suggestNextFreeIp->handle($segment);
            $macList = implode(' & ', array_map('strtoupper', $macs));

            $this->openOrTouch(
                type: ConflictType::DupIp,
                segment: $segment,
                ip: (string) $ip,
                macs: $macs,
                title: "IP {$ip} dipakai ".count($macs).' perangkat berbeda',
                summary: ($device ? "Perangkat terdaftar {$device->name} ({$ip}) " : "Alamat {$ip} ")
                    ."terdeteksi dengan MAC berbeda ({$macList}) di tabel ARP jalur {$segment->name}.",
                suggestion: ConflictType::DupIp->defaultSuggestion($freeIp),
                source: 'arp_history',
                device: $device,
            );
        }
    }

    private function evaluateReservationMisuse(Segment $segment): void
    {
        $reservations = IpReservation::query()->where('segment_id', $segment->id)->get();
        if ($reservations->isEmpty()) {
            return;
        }

        $recentArp = ArpObservation::query()->where('segment_id', $segment->id)->recent(30)->get();

        foreach ($recentArp as $obs) {
            $octet = $segment->octetOf($obs->ip);
            if ($octet === null) {
                continue;
            }

            $reservation = $reservations->first(fn (IpReservation $res) => $res->containsOctet($octet));
            if ($reservation === null) {
                continue;
            }

            $registered = Device::query()
                ->where('segment_id', $segment->id)
                ->where('ip', $obs->ip)
                ->first();

            if ($registered !== null && $registered->mac !== null && strtolower($registered->mac) === strtolower($obs->mac)) {
                continue;
            }

            $freeIp = $this->suggestNextFreeIp->handle($segment);

            $this->openOrTouch(
                type: ConflictType::ResMisuse,
                segment: $segment,
                ip: $obs->ip,
                macs: [$obs->mac],
                title: "IP reservasi {$obs->ip} dipakai perangkat tidak terdaftar",
                summary: "Alamat {$obs->ip} berada di range reservasi \"{$reservation->label}\" (.{$reservation->start_octet}–.{$reservation->end_octet}), tetapi dipakai oleh MAC ".strtoupper($obs->mac).'.',
                suggestion: ConflictType::ResMisuse->defaultSuggestion($freeIp),
                source: 'arp + reservation',
                device: $registered,
            );
        }
    }

    private function evaluateRegisteredMismatch(Segment $segment): void
    {
        $devices = Device::query()
            ->where('segment_id', $segment->id)
            ->whereNotNull('mac')
            ->get();

        foreach ($devices as $device) {
            $foreignObs = ArpObservation::query()
                ->where('segment_id', $segment->id)
                ->where('ip', $device->ip)
                ->where('mac', '!=', $device->mac)
                ->recent(15)
                ->first();

            if ($foreignObs === null) {
                continue;
            }

            // Skip if already flagged as DUP_IP with >=2 MACs.
            $recentCount = ArpObservation::query()
                ->where('segment_id', $segment->id)
                ->where('ip', $device->ip)
                ->recent(15)
                ->distinct('mac')
                ->count('mac');

            if ($recentCount >= 2) {
                continue;
            }

            $freeIp = $this->suggestNextFreeIp->handle($segment);

            $this->openOrTouch(
                type: ConflictType::RegMismatch,
                segment: $segment,
                ip: $device->ip,
                macs: array_values(array_filter([$device->mac, $foreignObs->mac])),
                title: "MAC pada {$device->ip} berbeda dengan inventaris ({$device->name})",
                summary: "{$device->name} terdaftar dengan MAC ".strtoupper((string) $device->mac).', namun ARP melihat MAC '.strtoupper($foreignObs->mac).'.',
                suggestion: ConflictType::RegMismatch->defaultSuggestion($freeIp),
                source: 'arp + device',
                device: $device,
            );
        }
    }

    private function evaluateStaticInPool(Segment $segment): void
    {
        if ($segment->pool_start === null || $segment->pool_end === null) {
            return;
        }

        $devices = Device::query()
            ->where('segment_id', $segment->id)
            ->where('is_static_lease', false)
            ->get();

        foreach ($devices as $device) {
            if (! $segment->isInPool($device->ip)) {
                continue;
            }

            $hasLease = DhcpLease::query()
                ->where('segment_id', $segment->id)
                ->where('ip', $device->ip)
                ->where('is_present', true)
                ->where('is_dynamic', false)
                ->exists();

            if ($hasLease) {
                continue;
            }

            $freeIp = $this->suggestNextFreeIp->handle($segment);

            $this->openOrTouch(
                type: ConflictType::StaticInPool,
                segment: $segment,
                ip: $device->ip,
                macs: $device->mac ? [$device->mac] : [],
                title: "IP static {$device->ip} ({$device->name}) berada di dalam pool DHCP",
                summary: "{$device->name} dipasang static di {$device->ip} yang masuk range pool DHCP ({$segment->pool_start}–{$segment->pool_end}) tanpa dikunci sebagai lease static.",
                suggestion: ConflictType::StaticInPool->defaultSuggestion($freeIp),
                source: 'arp + lease + pool',
                device: $device,
            );
        }
    }

    private function evaluateOfflineCameras(): void
    {
        $offlineCameras = Camera::query()
            ->with(['device.segment'])
            ->where('status', CameraStatus::Offline->value)
            ->get();

        foreach ($offlineCameras as $camera) {
            $device = $camera->device;
            $segment = $device?->segment;
            if ($device === null || $segment === null) {
                continue;
            }

            $this->openOrTouch(
                type: ConflictType::CamOffline,
                segment: $segment,
                ip: $device->ip,
                macs: $device->mac ? [$device->mac] : [],
                title: "Kamera {$device->name} ({$device->ip}) tidak merespons",
                summary: "Kamera {$device->name} di {$camera->location_group} ({$device->port}) gagal ping lebih dari 5 menit berturut-turut.",
                suggestion: ConflictType::CamOffline->defaultSuggestion(),
                source: 'fping',
                device: $device,
            );
        }
    }

    /**
     * @param  list<string>  $macs
     */
    private function openOrTouch(
        ConflictType $type,
        Segment $segment,
        string $ip,
        array $macs,
        string $title,
        string $summary,
        string $suggestion,
        string $source,
        ?Device $device = null,
    ): ConflictEvent {
        $existing = ConflictEvent::query()
            ->where('type', $type->value)
            ->where('segment_id', $segment->id)
            ->where('ip', $ip)
            ->where('status', ConflictStatus::Active->value)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'macs' => $macs,
                'summary' => $summary,
                'suggestion' => $suggestion,
                'last_detected_at' => now(),
            ]);

            return $existing;
        }

        $now = now();
        $event = ConflictEvent::create([
            'type' => $type,
            'severity' => $type->severity(),
            'status' => ConflictStatus::Active,
            'segment_id' => $segment->id,
            'ip' => $ip,
            'macs' => $macs,
            'title' => $title,
            'summary' => $summary,
            'suggestion' => $suggestion,
            'source' => $source,
            'device_id' => $device?->id,
            'opened_at' => $now,
            'last_detected_at' => $now,
        ]);

        ConflictEventLog::create([
            'conflict_event_id' => $event->id,
            'logged_at' => $now,
            'message' => "Terdeteksi pertama kali oleh {$source}: {$title}",
        ]);

        return $event;
    }
}
