"use client";

import {
  Activity,
  ArrowRight,
  Plus,
  Radar,
  RefreshCw,
  Search,
  TriangleAlert,
  Wifi,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { ErrorState } from "@/components/common/error-state";
import { Mono } from "@/components/common/mono";
import { StatCard } from "@/components/dashboard/stat-card";
import { SegmentDot } from "@/components/segment/segment-dot";
import { SeverityBadge } from "@/components/status/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { useDashboardSummary } from "@/hooks/use-dashboard";
import { useCan } from "@/hooks/use-me";
import { useScanArp, useSyncMikrotik } from "@/hooks/use-mikrotik-actions";
import { formatMac, formatNumber, formatPercent, formatRelativeTime } from "@/lib/format";
import { cn } from "@/lib/utils";

export function DashboardView() {
  const summary = useDashboardSummary();
  const canManageDevices = useCan("manage-devices");
  const syncMikrotik = useSyncMikrotik();
  const scanArp = useScanArp();
  const router = useRouter();
  const [searchQuery, setSearchQuery] = useState("");

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    const q = searchQuery.trim();
    if (!q) return;
    router.push(`/perangkat?q=${encodeURIComponent(q)}`);
  }

  if (summary.isPending) {
    return (
      <div className="flex flex-col gap-5">
        <Skeleton className="h-12 w-full" />
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {Array.from({ length: 4 }, (_, i) => (
            <Skeleton key={i} className="h-28 w-full" />
          ))}
        </div>
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (summary.isError || !summary.data) {
    return <ErrorState message={summary.error?.message} onRetry={() => void summary.refetch()} />;
  }

  const { router: routerInfo, stats, warnings, segments, uplinks, activities } = summary.data;

  return (
    <div className="flex flex-col gap-5">
      {/* Disconnected MikroTik Banner */}
      {routerInfo && !routerInfo.connected ? (
        <div className="border-warn-border bg-warn-bg flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3 text-xs">
          <div className="flex items-center gap-2.5">
            <TriangleAlert className="text-status-conflict size-4 shrink-0" aria-hidden="true" />
            <span>
              <strong>MikroTik tidak terhubung:</strong> {routerInfo.last_error} Data di bawah
              adalah snapshot terakhir ({formatRelativeTime(routerInfo.last_synced_at)}).
            </span>
          </div>
          <Button
            size="sm"
            variant="outline"
            onClick={() => syncMikrotik.mutate()}
            disabled={syncMikrotik.isPending}
          >
            Coba Sinkron Ulang
          </Button>
        </div>
      ) : null}

      {/* Top Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-2xl font-bold tracking-tight">Ringkasan Jaringan</h1>
          <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
            <span
              className="bg-status-online inline-block size-2 rounded-full"
              aria-hidden="true"
            />
            <span>
              {routerInfo?.name ?? "MikroTik-Core"} (<Mono>{routerInfo?.host ?? "10.10.99.1"}</Mono>
              )
            </span>
            <span>·</span>
            <span>Sinkron terakhir: {formatRelativeTime(routerInfo?.last_synced_at)}</span>
            <button
              type="button"
              onClick={() => syncMikrotik.mutate()}
              disabled={syncMikrotik.isPending}
              className="text-primary inline-flex items-center gap-1 hover:underline"
            >
              <RefreshCw
                className={cn("size-3", syncMikrotik.isPending && "animate-spin")}
                aria-hidden="true"
              />
              Sinkron
            </button>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2.5">
          <form onSubmit={handleSearch} className="relative min-w-[220px]">
            <Search
              className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
              aria-hidden="true"
            />
            <Input
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Cari IP / MAC / nama…"
              aria-label="Cari IP, MAC, atau nama perangkat"
              className="pl-9"
            />
          </form>

          <Button
            variant="outline"
            onClick={() => scanArp.mutate(null)}
            disabled={scanArp.isPending}
          >
            <Radar className="size-4" aria-hidden="true" />
            {scanArp.isPending ? "Memindai ARP…" : "Scan ARP"}
          </Button>

          {canManageDevices ? (
            <Button render={<Link href="/perangkat?tambah=1" />}>
              <Plus className="size-4" aria-hidden="true" />
              Tambah Perangkat
            </Button>
          ) : null}
        </div>
      </div>

      {/* 4 Stat Cards */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="IP Terpakai"
          value={`${formatNumber(stats.used_ips)} / ${formatNumber(stats.total_usable_ips)}`}
          subtitle={`${stats.static_devices} perangkat static · ${stats.dhcp_leases} lease DHCP`}
        />
        <StatCard
          label="Jalur Aktif"
          value={`${stats.active_segments} VLAN`}
          subtitle="Kantor · CCTV · WiFi Tamu · Manajemen"
        />
        <StatCard
          label="Kamera Online"
          value={`${stats.cameras_online} / ${stats.cameras_total}`}
          variant={stats.cameras_offline > 0 ? "default" : "success"}
          subtitle={
            <span>
              {stats.cameras_unstable > 0 ? `${stats.cameras_unstable} tak stabil · ` : ""}
              {stats.cameras_offline} offline
            </span>
          }
        />
        <StatCard
          label="Konflik & Rawan IP"
          value={stats.total_active_events}
          variant={stats.total_active_events > 0 ? "warning" : "default"}
          subtitle={`${stats.active_conflicts} konflik aktif · ${stats.active_risks} rawan`}
        />
      </div>

      {/* Active Conflict Warnings */}
      <section className="bg-card flex flex-col gap-3.5 rounded-xl border p-5">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <TriangleAlert className="text-status-conflict size-4" aria-hidden="true" />
            <h2 className="text-base font-semibold">
              Peringatan Bentrok &amp; Rawan IP ({stats.total_active_events})
            </h2>
          </div>
          <Link
            href="/konflik"
            className="text-primary inline-flex items-center gap-1 text-xs font-medium hover:underline"
          >
            Lihat semua di Log Konflik <ArrowRight className="size-3.5" aria-hidden="true" />
          </Link>
        </div>

        {warnings.length === 0 ? (
          <div className="bg-panel text-muted-foreground rounded-lg border p-4 text-xs">
            Tidak ada konflik IP atau peringatan aktif saat ini. Semua jalur berjalan normal.
          </div>
        ) : (
          <div className="grid gap-3 md:grid-cols-2">
            {warnings.map((w) => (
              <div
                key={w.id}
                className="bg-panel flex flex-col justify-between gap-3 rounded-xl border p-4 text-xs"
              >
                <div className="flex flex-col gap-1.5">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                      <SeverityBadge severity={w.severity} />
                      <Mono className="text-sm font-bold">{w.ip}</Mono>
                      {w.segment ? (
                        <span className="text-muted-foreground inline-flex items-center gap-1.5">
                          <SegmentDot color={w.segment.color} />
                          {w.segment.name}
                        </span>
                      ) : null}
                    </div>
                    <span className="text-muted-foreground">
                      {formatRelativeTime(w.last_detected_at)}
                    </span>
                  </div>
                  <div className="text-sm font-semibold">{w.title}</div>
                  <p className="text-muted-foreground leading-relaxed">{w.summary}</p>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-2.5">
                  <div className="flex flex-wrap gap-1">
                    {w.macs.map((mac) => (
                      <Mono
                        key={mac}
                        className="bg-card text-muted-foreground rounded border px-1.5 py-0.5 text-[11px]"
                      >
                        {formatMac(mac)}
                      </Mono>
                    ))}
                  </div>
                  <div className="flex items-center gap-2">
                    <Button
                      size="sm"
                      variant="outline"
                      render={
                        <Link
                          href={`/peta-ip?jalur=${w.segment?.slug ?? ""}&ip=${w.ip.split(".").pop()}`}
                        />
                      }
                    >
                      Peta IP
                    </Button>
                    <Button size="sm" render={<Link href={`/konflik?id=${w.id}`} />}>
                      Tangani
                    </Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Segments Table + Uplinks / Activities */}
      <div className="flex flex-wrap items-start gap-5">
        {/* Main column: Segments Table */}
        <section
          className="bg-card overflow-hidden rounded-xl border"
          style={{ flex: "999 1 560px" }}
        >
          <div className="flex items-center justify-between border-b px-5 py-4">
            <div>
              <h2 className="text-base font-semibold">Jalur Jaringan (VLAN)</h2>
              <p className="text-muted-foreground text-xs">
                Subnet, gateway, port MikroTik, keluar lewat ISP mana, dan pemakaian alamat IP.
              </p>
            </div>
            <Link href="/peta-ip" className="text-primary text-xs font-medium hover:underline">
              Buka Peta IP →
            </Link>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[680px] border-collapse text-[13px]">
              <thead>
                <tr className="text-muted-foreground border-b text-left text-xs">
                  <th className="px-5 py-3 font-medium">Jalur</th>
                  <th className="px-3 py-3 font-medium">VLAN</th>
                  <th className="px-3 py-3 font-medium">Subnet &amp; Gateway</th>
                  <th className="px-3 py-3 font-medium">Port MikroTik</th>
                  <th className="px-3 py-3 font-medium">Keluar Lewat</th>
                  <th className="px-5 py-3 font-medium">Pemakaian IP</th>
                </tr>
              </thead>
              <tbody>
                {segments.map((seg) => (
                  <tr key={seg.id} className="hover:bg-panel/60 border-t transition-colors">
                    <td className="px-5 py-3.5">
                      <Link
                        href={`/peta-ip?jalur=${seg.slug}`}
                        className="flex items-center gap-2.5 font-semibold hover:underline"
                      >
                        <SegmentDot color={seg.color} className="size-3" />
                        <span>{seg.name}</span>
                      </Link>
                    </td>
                    <td className="px-3 py-3.5">
                      <Mono>{seg.vlan_id ?? "—"}</Mono>
                    </td>
                    <td className="px-3 py-3.5">
                      <div>
                        <Mono className="font-semibold">{seg.subnet}</Mono>
                      </div>
                      <div className="text-muted-foreground text-xs">
                        GW <Mono>{seg.gateway}</Mono>
                      </div>
                    </td>
                    <td className="px-3 py-3.5">
                      <Mono className="text-xs">{seg.interface}</Mono>
                    </td>
                    <td className="px-3 py-3.5 text-xs">{seg.egress ?? "—"}</td>
                    <td className="px-5 py-3.5">
                      <div className="flex min-w-[140px] flex-col gap-1">
                        <div className="flex items-center justify-between text-xs">
                          <Mono>
                            {seg.used_ips}/{seg.usable_ips}
                          </Mono>
                          <Mono className="text-muted-foreground">
                            {formatPercent(seg.usage_percent)}
                          </Mono>
                        </div>
                        <div className="bg-background h-2 w-full overflow-hidden rounded-full">
                          <div
                            className="h-full rounded-full"
                            style={{
                              width: `${Math.min(100, seg.usage_percent)}%`,
                              backgroundColor: seg.color,
                            }}
                          />
                        </div>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        {/* Right column: Uplinks & Recent Activities */}
        <div className="flex flex-col gap-5" style={{ flex: "1 1 320px" }}>
          <section className="bg-card flex flex-col gap-3.5 rounded-xl border p-5">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Wifi className="text-primary size-4" aria-hidden="true" />
                <h2 className="text-base font-semibold">Uplink Internet (ISP)</h2>
              </div>
              <Link href="/route" className="text-primary text-xs hover:underline">
                Detail Route →
              </Link>
            </div>

            <div className="flex flex-col gap-2.5">
              {uplinks.map((u) => (
                <div
                  key={u.id}
                  className="bg-panel flex flex-col gap-1.5 rounded-lg border p-3.5 text-xs"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2 font-semibold">
                      <span
                        className={cn(
                          "size-2.5 rounded-full",
                          u.status === "up"
                            ? "bg-status-online"
                            : u.status === "standby"
                              ? "bg-status-risk"
                              : "bg-status-conflict",
                        )}
                      />
                      <span>{u.name}</span>
                      <span className="text-muted-foreground font-normal">
                        ({u.provider ?? u.interface})
                      </span>
                    </div>
                    <Mono className="text-status-online font-semibold">
                      {u.latency_ms ? `${u.latency_ms} ms` : "UP"}
                    </Mono>
                  </div>
                  <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-2 text-[11px]">
                    <span>
                      Port <Mono className="text-foreground">{u.interface}</Mono> · GW{" "}
                      <Mono className="text-foreground">{u.gateway}</Mono>
                    </span>
                    <Mono>
                      ↓ {u.rx_mbps} Mbps / ↑ {u.tx_mbps} Mbps
                    </Mono>
                  </div>
                </div>
              ))}
            </div>
          </section>

          <section className="bg-card flex flex-col gap-3.5 rounded-xl border p-5">
            <div className="flex items-center gap-2">
              <Activity className="text-primary size-4" aria-hidden="true" />
              <h2 className="text-base font-semibold">Aktivitas Terbaru</h2>
            </div>

            <ul className="flex flex-col gap-2.5 text-xs">
              {activities.map((act) => (
                <li
                  key={act.id}
                  className="flex items-start justify-between gap-3 border-b pb-2.5 last:border-b-0 last:pb-0"
                >
                  <div className="min-w-0">
                    <div className="font-medium">
                      <Mono className="text-primary">{act.action}</Mono>
                      {act.target ? <span> · {act.target}</span> : null}
                    </div>
                    <div className="text-muted-foreground text-[11px]">{act.actor}</div>
                  </div>
                  <span className="text-muted-foreground shrink-0 text-[11px]">
                    {formatRelativeTime(act.created_at)}
                  </span>
                </li>
              ))}
            </ul>
          </section>
        </div>
      </div>
    </div>
  );
}
