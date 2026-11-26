"use client";

import { CheckCircle2, Download, RefreshCw, Send, ShieldOff } from "lucide-react";
import { useState } from "react";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { Mono } from "@/components/common/mono";
import { PageHeader } from "@/components/common/page-header";
import { SeverityBadge } from "@/components/status/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { useSyncMikrotik, useUnblockMac } from "@/hooks/use-mikrotik-actions";
import {
  type SettingsGroupName,
  useAuditLogs,
  useBackups,
  useCreateBackup,
  useSettingsGroup,
  useTestMikrotikConnection,
  useTestTelegramNotification,
  useUpdateSettingsGroup,
} from "@/hooks/use-settings";
import { formatBytes, formatDateTime, formatMac, formatRelativeTime } from "@/lib/format";
import type { MacBlockEntry, SeverityCode } from "@/types/api";
import { SettingsNav, SettingsSection, SettingToggle } from "./settings-nav";

const RULE_ITEMS: {
  key: string;
  label: string;
  severity: SeverityCode;
  description: string;
}[] = [
  {
    key: "dup_ip",
    label: "DUP_IP — Satu IP dipakai >1 MAC",
    severity: "conflict",
    description:
      "Mendeteksi ≥2 MAC berbeda pada IP yang sama dalam 10 menit dari riwayat ARP atau balasan arping -D.",
  },
  {
    key: "res_misuse",
    label: "RES_MISUSE — IP reservasi dipakai perangkat lain",
    severity: "conflict",
    description:
      "Mendeteksi IP aktif di dalam range reservasi yang belum didaftarkan ke perangkat sah.",
  },
  {
    key: "reg_mismatch",
    label: "REG_MISMATCH — IP perangkat terdaftar dipakai MAC berbeda",
    severity: "conflict",
    description:
      "Mendeteksi ketidaksesuaian alamat MAC di tabel ARP dibanding inventaris perangkat NetMap.",
  },
  {
    key: "static_in_pool",
    label: "STATIC_IN_POOL — IP static di dalam pool DHCP",
    severity: "risk",
    description:
      "Mendeteksi perangkat yang dipasang static di dalam range pool DHCP tanpa lease static.",
  },
  {
    key: "out_of_subnet",
    label: "OUT_OF_SUBNET — IP di luar subnet jalurnya",
    severity: "risk",
    description:
      "Mendeteksi entri ARP pada interface VLAN yang tidak termasuk subnet jalur tersebut (indikasi rogue DHCP).",
  },
  {
    key: "mac_flap",
    label: "MAC_FLAP — MAC berpindah port switch",
    severity: "risk",
    description:
      "Mendeteksi MAC yang berpindah-pindah port akses switch dalam 15 menit (indikasi loop kabel).",
  },
  {
    key: "cam_offline",
    label: "CAM_OFFLINE — Kamera tidak merespons",
    severity: "risk",
    description: "Mendeteksi kamera CCTV yang gagal fping selama ≥5 menit berturut-turut.",
  },
  {
    key: "new_device",
    label: "NEW_DEVICE — Perangkat baru terdeteksi",
    severity: "info",
    description: "Mencatat alamat MAC baru yang pertama kali terlihat di jalur selain WiFi Tamu.",
  },
];

export function SettingsGroupView({ section }: { section: string }) {
  const group: SettingsGroupName =
    section === "sinkronisasi"
      ? "sync"
      : section === "aturan"
        ? "rules"
        : section === "notifikasi"
          ? "notifications"
          : section === "cctv"
            ? "cctv"
            : section === "backup"
              ? "backup"
              : "mikrotik";

  return (
    <div className="flex max-w-5xl flex-col gap-5">
      <PageHeader
        title="Pengaturan"
        description="Konfigurasi koneksi MikroTik RouterOS, jadwal sinkronisasi, aturan deteksi konflik, notifikasi Telegram, go2rtc CCTV, pengguna, dan backup."
      />

      <SettingsNav />

      {group === "mikrotik" ? <MikrotikSettingsPanel /> : null}
      {group === "sync" ? <SyncSettingsPanel /> : null}
      {group === "rules" ? <RulesSettingsPanel /> : null}
      {group === "notifications" ? <NotificationsSettingsPanel /> : null}
      {group === "cctv" ? <CctvSettingsPanel /> : null}
      {group === "backup" ? <BackupAndAuditPanel /> : null}
    </div>
  );
}

function MikrotikSettingsPanel() {
  const query = useSettingsGroup("mikrotik");
  if (query.isPending || !query.data) return <Skeleton className="h-72 w-full" />;

  return <MikrotikSettingsForm initialData={query.data} />;
}

function MikrotikSettingsForm({ initialData }: { initialData: Record<string, unknown> }) {
  const update = useUpdateSettingsGroup("mikrotik");
  const testConn = useTestMikrotikConnection();
  const syncNow = useSyncMikrotik();
  const unblockMac = useUnblockMac();

  const [name, setName] = useState(() => String(initialData.name ?? "MikroTik-Core"));
  const [host, setHost] = useState(() => String(initialData.host ?? "10.10.99.1"));
  const [port, setPort] = useState(() => String(initialData.port ?? 443));
  const [username, setUsername] = useState(() => String(initialData.username ?? "netmap-api"));
  const [password, setPassword] = useState("");
  const [useSsl, setUseSsl] = useState(() => Boolean(initialData.use_ssl ?? true));
  const [unblocking, setUnblocking] = useState<MacBlockEntry | null>(null);

  const routerStatus = initialData.router_status as
    | {
        name: string;
        version: string | null;
        uptime: string | null;
        cpu_load: number | null;
        last_synced_at: string | null;
      }
    | undefined;

  const macBlocks = (initialData.mac_blocks as MacBlockEntry[] | undefined) ?? [];

  return (
    <div className="flex flex-col gap-5">
      <SettingsSection
        title="Koneksi REST API MikroTik (RouterOS v7)"
        description="Gunakan akun khusus netmap-api yang dibatasi hanya dari IP mini PC."
        footer={
          <>
            <Button
              type="button"
              variant="outline"
              onClick={() => testConn.mutate()}
              disabled={testConn.isPending}
            >
              <CheckCircle2 className="size-4" aria-hidden="true" />
              {testConn.isPending ? "Menguji…" : "Tes Koneksi"}
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => syncNow.mutate()}
              disabled={syncNow.isPending}
            >
              <RefreshCw className="size-4" aria-hidden="true" />
              Sinkron Sekarang
            </Button>
            <Button
              type="button"
              onClick={() =>
                update.mutate({
                  name,
                  host,
                  port: Number(port),
                  username,
                  password: password || undefined,
                  use_ssl: useSsl,
                })
              }
              disabled={update.isPending}
            >
              Simpan Pengaturan
            </Button>
          </>
        }
      >
        {routerStatus ? (
          <div className="bg-panel flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3.5 text-xs">
            <div className="flex items-center gap-2">
              <span className="bg-status-online size-2.5 rounded-full" />
              <span className="font-semibold">
                Terhubung ke {routerStatus.name} — RouterOS {routerStatus.version ?? "v7"}
              </span>
            </div>
            <div className="text-muted-foreground flex flex-wrap gap-3">
              <span>Uptime: {routerStatus.uptime ?? "—"}</span>
              <span>CPU: {routerStatus.cpu_load ?? 0}%</span>
              <span>Sinkron: {formatRelativeTime(routerStatus.last_synced_at)}</span>
            </div>
          </div>
        ) : null}

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="mk-name">Nama Router</Label>
            <Input id="mk-name" value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="mk-host">Alamat IP Router</Label>
            <Input
              id="mk-host"
              value={host}
              onChange={(e) => setHost(e.target.value)}
              className="font-mono"
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="mk-port">Port REST API (www-ssl)</Label>
            <Input
              id="mk-port"
              type="number"
              value={port}
              onChange={(e) => setPort(e.target.value)}
              className="font-mono"
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="mk-user">Username API</Label>
            <Input
              id="mk-user"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              className="font-mono"
            />
          </div>
          <div className="flex flex-col gap-1.5 sm:col-span-2">
            <Label htmlFor="mk-pass">
              Password API{" "}
              {initialData.has_password ? (
                <span className="text-muted-foreground font-normal">
                  (sudah tersimpan terenkripsi — kosongkan jika tidak diubah)
                </span>
              ) : null}
            </Label>
            <Input
              id="mk-pass"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••••••"
            />
          </div>
        </div>

        <SettingToggle
          label="Gunakan HTTPS (www-ssl)"
          description="Wajib diaktifkan di produksi dengan sertifikat CA netmap-ca.crt."
          checked={useSsl}
          onCheckedChange={setUseSsl}
        />
      </SettingsSection>

      <SettingsSection
        title={`Daftar MAC yang Diblokir (${macBlocks.length})`}
        description="Alamat MAC yang sedang diblokir pada bridge filter MikroTik."
      >
        {macBlocks.length === 0 ? (
          <p className="text-muted-foreground text-xs">
            Tidak ada alamat MAC yang sedang diblokir.
          </p>
        ) : (
          <div className="flex flex-col gap-2">
            {macBlocks.map((block) => (
              <div
                key={block.id}
                className="bg-panel flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3.5 text-xs"
              >
                <div className="flex flex-col gap-0.5">
                  <div className="flex items-center gap-2">
                    <Mono className="text-sm font-bold">{formatMac(block.mac)}</Mono>
                    {block.ip ? (
                      <span className="text-muted-foreground">
                        (terakhir di <Mono>{block.ip}</Mono>)
                      </span>
                    ) : null}
                  </div>
                  <span className="text-muted-foreground">{block.reason ?? "—"}</span>
                </div>
                <Button size="sm" variant="outline" onClick={() => setUnblocking(block)}>
                  <ShieldOff className="size-3.5" aria-hidden="true" />
                  Buka Blokir
                </Button>
              </div>
            ))}
          </div>
        )}
      </SettingsSection>

      <ConfirmDialog
        open={unblocking !== null}
        onOpenChange={(open) => !open && setUnblocking(null)}
        title="Buka blokir MAC ini?"
        description={
          <>
            Aturan drop di bridge filter MikroTik untuk MAC{" "}
            <Mono>{formatMac(unblocking?.mac)}</Mono> akan dihapus dan perangkat dapat kembali
            terhubung.
          </>
        }
        confirmLabel="Buka Blokir"
        onConfirm={async () => {
          if (!unblocking) return;
          await unblockMac.mutateAsync(unblocking.id);
        }}
      />
    </div>
  );
}

function SyncSettingsPanel() {
  const query = useSettingsGroup("sync");
  if (query.isPending || !query.data) return <Skeleton className="h-64 w-full" />;

  return <SyncSettingsForm initialData={query.data} />;
}

function SyncSettingsForm({ initialData }: { initialData: Record<string, unknown> }) {
  const update = useUpdateSettingsGroup("sync");

  const [pollInterval, setPollInterval] = useState(() =>
    String(initialData.poll_interval_seconds ?? 30),
  );
  const [arpScanInterval, setArpScanInterval] = useState(() =>
    String(initialData.arp_scan_interval_minutes ?? 10),
  );
  const [pingInterval, setPingInterval] = useState(() =>
    String(initialData.ping_interval_seconds ?? 30),
  );
  const [snmpEnabled, setSnmpEnabled] = useState(() => Boolean(initialData.snmp_enabled ?? true));

  return (
    <SettingsSection
      title="Jadwal Sinkronisasi & Scanner"
      description="Mengatur frekuensi penarikan data dari MikroTik, ping fping, dan probe arping per jalur."
      footer={
        <Button
          onClick={() =>
            update.mutate({
              poll_interval_seconds: Number(pollInterval),
              arp_scan_interval_minutes: Number(arpScanInterval),
              ping_interval_seconds: Number(pingInterval),
              snmp_enabled: snmpEnabled,
            })
          }
          disabled={update.isPending}
        >
          Simpan Pengaturan
        </Button>
      }
    >
      <div className="grid gap-4 sm:grid-cols-3">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="sync-poll">Interval Poll MikroTik (detik)</Label>
          <Input
            id="sync-poll"
            type="number"
            min={10}
            max={300}
            value={pollInterval}
            onChange={(e) => setPollInterval(e.target.value)}
            className="font-mono"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="sync-arp">Interval Scan ARP Aktif (menit)</Label>
          <Input
            id="sync-arp"
            type="number"
            min={1}
            max={60}
            value={arpScanInterval}
            onChange={(e) => setArpScanInterval(e.target.value)}
            className="font-mono"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="sync-ping">Interval Ping Perangkat (detik)</Label>
          <Input
            id="sync-ping"
            type="number"
            min={10}
            max={300}
            value={pingInterval}
            onChange={(e) => setPingInterval(e.target.value)}
            className="font-mono"
          />
        </div>
      </div>

      <SettingToggle
        label="Poller SNMP Switch Managed"
        description="Baca tabel FDB (dot1qTpFdbTable) dari switch managed untuk memetakan MAC ke nomor port akses."
        checked={snmpEnabled}
        onCheckedChange={setSnmpEnabled}
      />
    </SettingsSection>
  );
}

function RulesSettingsPanel() {
  const query = useSettingsGroup("rules");
  if (query.isPending || !query.data) return <Skeleton className="h-80 w-full" />;

  return <RulesSettingsForm initialData={query.data} />;
}

function RulesSettingsForm({ initialData }: { initialData: Record<string, unknown> }) {
  const update = useUpdateSettingsGroup("rules");
  const [rulesState, setRulesState] = useState<Record<string, boolean>>(() => {
    const next: Record<string, boolean> = {};
    for (const item of RULE_ITEMS) {
      next[item.key] = Boolean(initialData[item.key] ?? true);
    }
    return next;
  });

  return (
    <SettingsSection
      title="Aturan Deteksi Konflik IP"
      description="Nyalakan atau matikan masing-masing aturan deteksi. Event aktif otomatis ditutup bila kondisi hilang ≥ 15 menit."
      footer={
        <Button onClick={() => update.mutate(rulesState)} disabled={update.isPending}>
          Simpan Aturan
        </Button>
      }
    >
      <div className="flex flex-col gap-2.5">
        {RULE_ITEMS.map((rule) => (
          <SettingToggle
            key={rule.key}
            label={rule.label}
            description={rule.description}
            badge={<SeverityBadge severity={rule.severity} />}
            checked={rulesState[rule.key] ?? true}
            onCheckedChange={(checked) =>
              setRulesState((prev) => ({ ...prev, [rule.key]: checked }))
            }
          />
        ))}
      </div>
    </SettingsSection>
  );
}

function NotificationsSettingsPanel() {
  const query = useSettingsGroup("notifications");
  if (query.isPending || !query.data) return <Skeleton className="h-72 w-full" />;

  return <NotificationsSettingsForm initialData={query.data} />;
}

function NotificationsSettingsForm({ initialData }: { initialData: Record<string, unknown> }) {
  const update = useUpdateSettingsGroup("notifications");
  const testNotif = useTestTelegramNotification();

  const [enabled, setEnabled] = useState(() => Boolean(initialData.telegram_enabled ?? true));
  const [botToken, setBotToken] = useState("");
  const [chatId, setChatId] = useState(() =>
    String(initialData.telegram_chat_id ?? "-100239481029"),
  );
  const [onConflict, setOnConflict] = useState(() =>
    Boolean(initialData.notify_on_conflict ?? true),
  );
  const [onCameraOffline, setOnCameraOffline] = useState(() =>
    Boolean(initialData.notify_on_camera_offline ?? true),
  );
  const [onFailover, setOnFailover] = useState(() =>
    Boolean(initialData.notify_on_uplink_failover ?? true),
  );

  return (
    <SettingsSection
      title="Notifikasi Telegram"
      description="Kirim peringatan instan ke grup Telegram IT saat terjadi bentrok IP, kamera offline > 5 menit, atau ISP pindah ke cadangan (batas anti-spam 1× per 30 menit)."
      footer={
        <>
          <Button
            type="button"
            variant="outline"
            onClick={() => testNotif.mutate()}
            disabled={testNotif.isPending}
          >
            <Send className="size-4" aria-hidden="true" />
            {testNotif.isPending ? "Mengirim…" : "Kirim Notifikasi Tes"}
          </Button>
          <Button
            type="button"
            onClick={() =>
              update.mutate({
                telegram_enabled: enabled,
                telegram_bot_token: botToken || undefined,
                telegram_chat_id: chatId,
                notify_on_conflict: onConflict,
                notify_on_camera_offline: onCameraOffline,
                notify_on_uplink_failover: onFailover,
              })
            }
            disabled={update.isPending}
          >
            Simpan Pengaturan
          </Button>
        </>
      }
    >
      <SettingToggle
        label="Aktifkan Notifikasi Telegram"
        checked={enabled}
        onCheckedChange={setEnabled}
      />

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="tg-token">
            Bot Token Telegram{" "}
            {initialData.has_telegram_bot_token ? (
              <span className="text-muted-foreground font-normal">(tersimpan)</span>
            ) : null}
          </Label>
          <Input
            id="tg-token"
            type="password"
            value={botToken}
            onChange={(e) => setBotToken(e.target.value)}
            placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="tg-chat">Chat ID Grup / Channel</Label>
          <Input
            id="tg-chat"
            value={chatId}
            onChange={(e) => setChatId(e.target.value)}
            className="font-mono"
          />
        </div>
      </div>

      <div className="grid gap-2.5 sm:grid-cols-3">
        <SettingToggle
          label="Konflik IP Baru"
          checked={onConflict}
          onCheckedChange={setOnConflict}
        />
        <SettingToggle
          label="Kamera Offline ≥ 5 mnt"
          checked={onCameraOffline}
          onCheckedChange={setOnCameraOffline}
        />
        <SettingToggle label="ISP Failover" checked={onFailover} onCheckedChange={setOnFailover} />
      </div>
    </SettingsSection>
  );
}

function CctvSettingsPanel() {
  const query = useSettingsGroup("cctv");
  if (query.isPending || !query.data) return <Skeleton className="h-72 w-full" />;

  return <CctvSettingsForm initialData={query.data} />;
}

function CctvSettingsForm({ initialData }: { initialData: Record<string, unknown> }) {
  const update = useUpdateSettingsGroup("cctv");

  const [go2rtcUrl, setGo2rtcUrl] = useState(() =>
    String(initialData.go2rtc_url ?? "http://127.0.0.1:1984"),
  );
  const [cameraUsername, setCameraUsername] = useState(() =>
    String(initialData.camera_username ?? "viewer"),
  );
  const [cameraPassword, setCameraPassword] = useState("");
  const [maxStreams, setMaxStreams] = useState(() =>
    String(initialData.max_concurrent_streams ?? 16),
  );

  return (
    <SettingsSection
      title="Stream CCTV (go2rtc)"
      description="Konfigurasi gateway RTSP → WebRTC. Kredensial kamera hanya disimpan di backend dan tidak pernah dikirim ke browser."
      footer={
        <Button
          onClick={() =>
            update.mutate({
              go2rtc_url: go2rtcUrl,
              camera_username: cameraUsername,
              camera_password: cameraPassword || undefined,
              max_concurrent_streams: Number(maxStreams),
            })
          }
          disabled={update.isPending}
        >
          Simpan Pengaturan
        </Button>
      }
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="cctv-url">URL Internal go2rtc API</Label>
          <Input
            id="cctv-url"
            value={go2rtcUrl}
            onChange={(e) => setGo2rtcUrl(e.target.value)}
            className="font-mono"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="cctv-max">Maksimal Stream Bersamaan</Label>
          <Input
            id="cctv-max"
            type="number"
            min={1}
            max={32}
            value={maxStreams}
            onChange={(e) => setMaxStreams(e.target.value)}
            className="font-mono"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="cctv-user">Username Viewer Kamera / NVR</Label>
          <Input
            id="cctv-user"
            value={cameraUsername}
            onChange={(e) => setCameraUsername(e.target.value)}
            className="font-mono"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="cctv-pass">Password Viewer Kamera</Label>
          <Input
            id="cctv-pass"
            type="password"
            value={cameraPassword}
            onChange={(e) => setCameraPassword(e.target.value)}
            placeholder="••••••••••••"
          />
        </div>
      </div>

      <div className="bg-panel flex flex-col gap-2 rounded-lg border p-3.5 text-xs">
        <span className="font-semibold">Pola URL RTSP per Merek</span>
        <div className="text-muted-foreground flex flex-col gap-1 font-mono text-[11px]">
          <div>
            Hikvision: rtsp://viewer:***@&#123;ip&#125;:554/Streaming/Channels/101 (main) / 102
            (sub)
          </div>
          <div>
            Dahua: rtsp://viewer:***@&#123;ip&#125;:554/cam/realmonitor?channel=1&amp;subtype=0|1
          </div>
        </div>
      </div>
    </SettingsSection>
  );
}

function BackupAndAuditPanel() {
  const backups = useBackups();
  const createBackup = useCreateBackup();
  const [auditQuery, setAuditQuery] = useState("");
  const auditLogs = useAuditLogs(auditQuery);

  return (
    <div className="flex flex-col gap-5">
      <SettingsSection
        title="Backup Harian Database & Konfigurasi MikroTik"
        description="Otomatis berjalan setiap pukul 02:00 WIB (pg_dump PostgreSQL + /export RouterOS) dengan retensi 14 hari."
        footer={
          <Button onClick={() => createBackup.mutate()} disabled={createBackup.isPending}>
            {createBackup.isPending ? "Membuat Backup…" : "Backup Sekarang"}
          </Button>
        }
      >
        <div className="flex flex-col gap-2">
          {(backups.data ?? []).map((b) => (
            <div
              key={b.id}
              className="bg-panel flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3.5 text-xs"
            >
              <div>
                <Mono className="font-semibold">{b.filename}</Mono>
                <div className="text-muted-foreground text-[11px]">
                  {formatBytes(b.size_bytes)} · {formatDateTime(b.created_at)} · {b.created_by_name}
                </div>
              </div>
              <Button
                size="sm"
                variant="outline"
                render={<a href={`/api/v1/backups/${b.id}/download`} download />}
              >
                <Download className="size-3.5" aria-hidden="true" />
                Unduh
              </Button>
            </div>
          ))}
        </div>
      </SettingsSection>

      <SettingsSection
        title="Audit Log (Jejak Aksi)"
        description="Catatan permanen semua perubahan perangkat, pengguna, pengaturan, dan aksi tulis ke MikroTik."
      >
        <Input
          value={auditQuery}
          onChange={(e) => setAuditQuery(e.target.value)}
          placeholder="Filter aksi atau target…"
          aria-label="Cari di audit log"
        />

        <div className="overflow-x-auto">
          <table className="w-full min-w-[640px] border-collapse text-xs">
            <thead>
              <tr className="text-muted-foreground border-b text-left">
                <th className="py-2.5 pr-3 font-medium">Waktu</th>
                <th className="px-3 py-2.5 font-medium">Pengguna</th>
                <th className="px-3 py-2.5 font-medium">Aksi</th>
                <th className="px-3 py-2.5 font-medium">Target</th>
                <th className="py-2.5 pl-3 font-medium">IP</th>
              </tr>
            </thead>
            <tbody>
              {(auditLogs.data ?? []).map((log) => (
                <tr key={log.id} className="border-t">
                  <td className="text-muted-foreground py-2.5 pr-3 whitespace-nowrap">
                    <Mono>{formatDateTime(log.created_at)}</Mono>
                  </td>
                  <td className="px-3 py-2.5 font-medium">{log.user?.name ?? "Sistem / CLI"}</td>
                  <td className="px-3 py-2.5">
                    <Mono className="text-primary">{log.action}</Mono>
                  </td>
                  <td className="px-3 py-2.5">{log.target ?? "—"}</td>
                  <td className="text-muted-foreground py-2.5 pl-3">
                    <Mono>{log.ip ?? "—"}</Mono>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </SettingsSection>
    </div>
  );
}
