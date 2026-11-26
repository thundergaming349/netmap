"use client";

import {
  Camera as CameraIcon,
  Clock,
  Download,
  ExternalLink,
  Film,
  Grid2x2,
  Grid3x3,
  LayoutGrid,
  Radio,
  RotateCcw,
  Route,
  Square,
  X,
} from "lucide-react";
import { useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { ErrorState } from "@/components/common/error-state";
import { Mono } from "@/components/common/mono";
import { PageHeader } from "@/components/common/page-header";
import { StatCard } from "@/components/dashboard/stat-card";
import { CameraStatusBadge } from "@/components/status/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import {
  clearCctvClientCache,
  useCameraRecordings,
  useCameras,
  useCaptureSnapshot,
  useNvrs,
  usePingCamera,
} from "@/hooks/use-cctv";
import { useCan } from "@/hooks/use-me";
import { useRestartPoe } from "@/hooks/use-mikrotik-actions";
import { formatBytes } from "@/lib/format";
import { confirmToast } from "@/lib/toast";
import { cn } from "@/lib/utils";
import type { Camera } from "@/types/api";
import { CameraGrid } from "./camera-grid";
import { CameraPlayer } from "./camera-player";
import { NvrStatus } from "./nvr-status";

const LAYOUT_OPTIONS = [
  { value: 1 as const, label: "1×1", icon: Square },
  { value: 4 as const, label: "2×2", icon: Grid2x2 },
  { value: 9 as const, label: "3×3", icon: Grid3x3 },
  { value: 16 as const, label: "4×4", icon: LayoutGrid },
];

function todayJakarta(): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Jakarta" }).format(new Date());
}

function minRecordingDateJakarta(): string {
  const cutoff = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
  return new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Jakarta" }).format(cutoff);
}

export function CctvView() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const queryClient = useQueryClient();
  const canViewNetwork = useCan("view-network");
  const canWriteMikrotik = useCan("mikrotik-write");

  // Wipe all client-side CCTV cache immediately when leaving the Live CCTV view
  useEffect(() => {
    return () => {
      clearCctvClientCache(queryClient);
    };
  }, [queryClient]);

  const groupParam = searchParams.get("lokasi") ?? "all";
  const layoutParam = Number.parseInt(searchParams.get("layout") ?? "9", 10);
  const layout: 1 | 4 | 9 | 16 =
    layoutParam === 1 || layoutParam === 4 || layoutParam === 9 || layoutParam === 16
      ? layoutParam
      : 9;

  const [page, setPage] = useState(1);
  const [expandedCameraId, setExpandedCameraId] = useState<number | null>(null);
  const [selectedArchiveCameraId, setSelectedArchiveCameraId] = useState<number | null>(null);
  const [recordingDate, setRecordingDate] = useState<string>(todayJakarta);

  const camerasQuery = useCameras(groupParam);
  const nvrsQuery = useNvrs();
  const captureSnapshot = useCaptureSnapshot();
  const pingCamera = usePingCamera();
  const restartPoe = useRestartPoe();

  const allFiltered = camerasQuery.data?.data ?? [];
  const totalPages = Math.max(1, Math.ceil(allFiltered.length / layout));
  const currentPage = Math.min(page, totalPages);
  const visibleCameras = allFiltered.slice((currentPage - 1) * layout, currentPage * layout);

  const expandedCamera =
    expandedCameraId !== null
      ? (allFiltered.find((c) => c.id === expandedCameraId) ?? visibleCameras[0] ?? null)
      : null;

  const activeCameraForRecordings =
    expandedCamera ??
    (selectedArchiveCameraId !== null
      ? allFiltered.find((c) => c.id === selectedArchiveCameraId)
      : null) ??
    visibleCameras[0] ??
    allFiltered[0] ??
    null;

  const recordingsQuery = useCameraRecordings(activeCameraForRecordings?.id ?? null, recordingDate);

  function goToPage(nextPage: number) {
    const safePage = Math.max(1, Math.min(totalPages, nextPage));
    setPage(safePage);
    const nextSlice = allFiltered.slice((safePage - 1) * layout, safePage * layout);
    const firstOnPage = nextSlice[0] ?? null;
    if (expandedCameraId !== null && firstOnPage) {
      setExpandedCameraId(firstOnPage.id);
    }
    setSelectedArchiveCameraId(firstOnPage?.id ?? null);
  }

  function selectSpotlightCamera(cam: Camera) {
    setExpandedCameraId(cam.id);
    setSelectedArchiveCameraId(cam.id);
    const idx = allFiltered.findIndex((c) => c.id === cam.id);
    if (idx >= 0) {
      const targetPage = Math.floor(idx / layout) + 1;
      if (targetPage !== currentPage) {
        setPage(targetPage);
      }
    }
  }

  function stepSpotlightCamera(delta: -1 | 1) {
    if (!expandedCamera || allFiltered.length === 0) return;
    const idx = allFiltered.findIndex((c) => c.id === expandedCamera.id);
    const nextIdx = (idx + delta + allFiltered.length) % allFiltered.length;
    const nextCam = allFiltered[nextIdx];
    if (nextCam) {
      selectSpotlightCamera(nextCam);
    }
  }

  function updateFilter(nextGroup: string, nextLayout: 1 | 4 | 9 | 16) {
    setPage(1);
    setExpandedCameraId(null);
    setSelectedArchiveCameraId(null);
    const params = new URLSearchParams(searchParams.toString());
    if (nextGroup === "all") params.delete("lokasi");
    else params.set("lokasi", nextGroup);
    params.set("layout", String(nextLayout));
    router.replace(`/cctv?${params.toString()}`, { scroll: false });
  }

  if (camerasQuery.isPending) {
    return (
      <div className="flex flex-col gap-5">
        <Skeleton className="h-12 w-72" />
        <Skeleton className="h-96 w-full" />
      </div>
    );
  }

  if (camerasQuery.isError || !camerasQuery.data) {
    return (
      <ErrorState
        message={camerasQuery.error?.message}
        onRetry={() => void camerasQuery.refetch()}
      />
    );
  }

  const meta = camerasQuery.data.meta;
  const troubledCameras = allFiltered.filter((c) => c.status !== "online");

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <PageHeader
          title="Live CCTV & Rekaman Per 1 Jam"
          description="Pantau stream kamera secara langsung via go2rtc WebRTC/MP4 dan unduh arsip rekaman video setiap durasi 1 jam."
        />

        {/* Layout Selector */}
        <div className="flex items-center gap-1.5">
          {LAYOUT_OPTIONS.map((opt) => {
            const Icon = opt.icon;
            const active = layout === opt.value;
            return (
              <Button
                key={opt.value}
                variant={active ? "default" : "outline"}
                size="sm"
                onClick={() => updateFilter(groupParam, opt.value)}
              >
                <Icon className="size-4" aria-hidden="true" />
                {opt.label}
              </Button>
            );
          })}
        </div>
      </div>

      {/* Location Group Filter Chips */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => updateFilter("all", layout)}
            className={cn(
              "flex min-h-10 items-center gap-2 rounded-lg border px-3.5 py-2 text-xs font-medium transition-colors",
              groupParam === "all"
                ? "bg-secondary text-foreground border-primary/60 font-semibold"
                : "bg-card text-muted-foreground hover:text-foreground",
            )}
          >
            Semua Lokasi
            <Mono className="text-muted-foreground">({meta.total})</Mono>
          </button>

          {meta.groups.map((g) => {
            const active = groupParam.toLowerCase() === g.name.toLowerCase();
            return (
              <button
                key={g.name}
                type="button"
                onClick={() => updateFilter(g.name, layout)}
                className={cn(
                  "flex min-h-10 items-center gap-2 rounded-lg border px-3.5 py-2 text-xs font-medium transition-colors",
                  active
                    ? "bg-secondary text-foreground border-primary/60 font-semibold"
                    : "bg-card text-muted-foreground hover:text-foreground",
                )}
              >
                <span>{g.name}</span>
                <Mono className="text-muted-foreground">({g.count})</Mono>
              </button>
            );
          })}
        </div>

        {totalPages > 1 ? (
          <div className="flex items-center gap-2 text-xs">
            <Button
              size="sm"
              variant="outline"
              disabled={currentPage <= 1}
              onClick={() => goToPage(currentPage - 1)}
            >
              Sebelumnya
            </Button>
            <Mono>
              Hal {currentPage}/{totalPages}
            </Mono>
            <Button
              size="sm"
              variant="outline"
              disabled={currentPage >= totalPages}
              onClick={() => goToPage(currentPage + 1)}
            >
              Berikutnya
            </Button>
          </div>
        ) : null}
      </div>

      {/* Expanded Camera Spotlight + 1-Hour Recordings Download Panel */}
      {expandedCamera ? (
        <div className="bg-card border-primary/50 flex flex-col gap-5 rounded-xl border p-5">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-3">
            <div className="flex flex-wrap items-center gap-3">
              <CameraStatusBadge status={expandedCamera.status} />
              <div>
                <div className="flex items-center gap-2">
                  <h2 className="text-base font-semibold">{expandedCamera.name}</h2>
                  <span className="bg-secondary text-foreground rounded px-2 py-0.5 font-mono text-[10px] font-semibold uppercase">
                    {expandedCamera.brand === "hikvision" ? "HIKVISION" : "ONVIF/IPC"}
                  </span>
                </div>
                <div className="text-muted-foreground text-xs">
                  <Mono>{expandedCamera.ip}</Mono> · {expandedCamera.location_group} · Channel{" "}
                  {expandedCamera.channel} ({expandedCamera.nvr_name ?? "NVR"})
                </div>
              </div>

              <select
                value={expandedCamera.id}
                onChange={(e) => {
                  const found = allFiltered.find((c) => c.id === Number(e.target.value));
                  if (found) selectSpotlightCamera(found);
                }}
                aria-label="Pilih kamera CCTV"
                className="bg-panel text-foreground border-border h-9 rounded-lg border px-2.5 font-mono text-xs"
              >
                {allFiltered.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} ({c.ip}) — {c.brand === "hikvision" ? "HIKVISION" : "ONVIF/IPC"}
                  </option>
                ))}
              </select>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Button size="sm" variant="outline" onClick={() => stepSpotlightCamera(-1)}>
                ◀ Kamera Sebelumnya
              </Button>
              <Button size="sm" variant="outline" onClick={() => stepSpotlightCamera(1)}>
                Kamera Berikutnya ▶
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => captureSnapshot.mutate(expandedCamera.id)}
                disabled={captureSnapshot.isPending}
              >
                <CameraIcon className="size-4" aria-hidden="true" />
                Snapshot
              </Button>
              {expandedCamera.ip ? (
                <Button
                  size="sm"
                  variant="outline"
                  render={
                    <a href={`http://${expandedCamera.ip}`} target="_blank" rel="noreferrer" />
                  }
                >
                  <ExternalLink className="size-4" aria-hidden="true" />
                  Buka Web Kamera ({expandedCamera.ip})
                </Button>
              ) : null}
              {canViewNetwork ? (
                <Button
                  size="sm"
                  variant="outline"
                  render={<Link href={`/route?device=${expandedCamera.device_id}`} />}
                >
                  <Route className="size-4" aria-hidden="true" />
                  Trace Jalur
                </Button>
              ) : null}
              <Button
                size="icon-sm"
                variant="ghost"
                aria-label="Tutup tampilan besar"
                onClick={() => setExpandedCameraId(null)}
              >
                <X className="size-4" />
              </Button>
            </div>
          </div>

          <div className="grid gap-5 lg:grid-cols-2">
            <div className="overflow-hidden rounded-xl border">
              <CameraPlayer camera={expandedCamera} quality="main" />
            </div>

            {/* 1-Hour Segmented Recordings List for this Camera */}
            <div className="bg-panel flex flex-col gap-3 rounded-xl border p-4">
              <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-3">
                <div className="flex items-center gap-2">
                  <Film className="text-primary size-4" aria-hidden="true" />
                  <div>
                    <h3 className="text-sm font-semibold">
                      Rekaman Per Durasi 1 Jam — {expandedCamera.name}
                    </h3>
                    <p className="text-muted-foreground text-[11px]">
                      Merekam otomatis 24/7 di latar belakang · Retensi otomatis 7 hari terakhir.
                    </p>
                  </div>
                </div>
                <Input
                  type="date"
                  value={recordingDate}
                  min={minRecordingDateJakarta()}
                  max={todayJakarta()}
                  onChange={(e) => setRecordingDate(e.target.value)}
                  className="w-40 font-mono text-xs"
                  aria-label="Pilih tanggal rekaman"
                />
              </div>

              <div className="flex max-h-72 flex-col gap-2 overflow-y-auto pr-1">
                {recordingsQuery.isPending ? (
                  Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-12 w-full" />)
                ) : (recordingsQuery.data ?? []).length === 0 ? (
                  <p className="text-muted-foreground py-6 text-center text-xs">
                    {recordingDate === todayJakarta()
                      ? "Rekaman jam berjalan sedang disiapkan di latar belakang..."
                      : "Belum ada arsip rekaman pada tanggal ini (rekaman > 7 hari dihapus otomatis)."}
                  </p>
                ) : (
                  (recordingsQuery.data ?? []).map((rec) => (
                    <div
                      key={rec.id}
                      className="bg-card flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3.5 py-2.5 text-xs"
                    >
                      <div className="flex flex-col gap-0.5">
                        <div className="flex items-center gap-2 font-semibold">
                          <Clock className="text-primary size-3.5" aria-hidden="true" />
                          <Mono>{rec.slot_label}</Mono>
                          <span
                            className={cn(
                              "rounded px-1.5 py-0.5 text-[10px] font-semibold",
                              rec.status === "recording"
                                ? "bg-status-conflict text-status-conflict-fg"
                                : "bg-status-online-bg text-status-online-fg",
                            )}
                          >
                            {rec.duration_label}
                          </span>
                        </div>
                        <div className="text-muted-foreground text-[11px]">
                          <Mono>{rec.filename}</Mono> · {formatBytes(rec.size_bytes)}
                        </div>
                      </div>

                      <Button
                        size="sm"
                        variant="outline"
                        render={<a href={rec.download_url} download={rec.filename} />}
                      >
                        <Download className="size-3.5" aria-hidden="true" />
                        Unduh 1 Jam (.mp4)
                      </Button>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      ) : null}

      {/* Main 2-column layout: Camera Grid + Right Sidebar (Stats, NVR, Hourly Recordings Quick Access, Troubled Cameras) */}
      <div className="flex flex-wrap items-start gap-5">
        <div className="flex flex-col gap-5" style={{ flex: "999 1 560px" }}>
          <CameraGrid
            cameras={visibleCameras}
            layout={layout}
            onExpand={(cam) => selectSpotlightCamera(cam)}
          />

          {/* Always-visible 1-Hour Recording Archive Section */}
          {activeCameraForRecordings && !expandedCamera ? (
            <section className="bg-card flex flex-col gap-4 rounded-xl border p-5">
              <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-3.5">
                <div className="flex items-center gap-2.5">
                  <Film className="text-primary size-5" aria-hidden="true" />
                  <div>
                    <h2 className="text-base font-semibold">
                      Arsip Rekaman CCTV (Unduh per Durasi 1 Jam)
                    </h2>
                    <p className="text-muted-foreground text-xs">
                      Menampilkan potongan rekaman 1 jam untuk{" "}
                      <strong className="text-foreground">{activeCameraForRecordings.name}</strong>{" "}
                      (<Mono>{activeCameraForRecordings.ip}</Mono>). Merekam otomatis di latar
                      belakang · Rekaman &gt; 7 hari dihapus otomatis.
                    </p>
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  <select
                    value={activeCameraForRecordings.id}
                    onChange={(e) => setSelectedArchiveCameraId(Number(e.target.value))}
                    aria-label="Pilih kamera untuk arsip rekaman"
                    className="bg-panel text-foreground border-border h-9 rounded-lg border px-2.5 font-mono text-xs"
                  >
                    {allFiltered.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name} ({c.ip})
                      </option>
                    ))}
                  </select>
                  <Input
                    type="date"
                    value={recordingDate}
                    min={minRecordingDateJakarta()}
                    max={todayJakarta()}
                    onChange={(e) => setRecordingDate(e.target.value)}
                    className="w-40 font-mono text-xs"
                    aria-label="Tanggal rekaman"
                  />
                </div>
              </div>

              {(recordingsQuery.data ?? []).length === 0 ? (
                <p className="text-muted-foreground py-6 text-center text-xs">
                  {recordingDate === todayJakarta()
                    ? "Rekaman jam berjalan sedang disiapkan di latar belakang..."
                    : "Belum ada arsip rekaman pada tanggal ini (rekaman > 7 hari dihapus otomatis)."}
                </p>
              ) : (
                <div className="grid gap-2.5 sm:grid-cols-2">
                  {(recordingsQuery.data ?? []).slice(0, 8).map((rec) => (
                    <div
                      key={rec.id}
                      className="bg-panel flex items-center justify-between gap-3 rounded-lg border p-3.5 text-xs"
                    >
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2 font-semibold">
                          <Clock className="text-primary size-3.5 shrink-0" aria-hidden="true" />
                          <Mono>{rec.slot_label}</Mono>
                        </div>
                        <div className="text-muted-foreground mt-0.5 truncate text-[11px]">
                          {rec.duration_label} · <Mono>{formatBytes(rec.size_bytes)}</Mono>
                        </div>
                      </div>

                      <Button
                        size="sm"
                        variant="outline"
                        render={<a href={rec.download_url} download={rec.filename} />}
                      >
                        <Download className="size-3.5" aria-hidden="true" />
                        Unduh 1 Jam
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </section>
          ) : null}
        </div>

        <aside className="flex flex-col gap-4" style={{ flex: "1 1 300px" }}>
          <div className="grid grid-cols-3 gap-2.5">
            <StatCard label="Online" value={meta.online} variant="success" />
            <StatCard
              label="Tak Stabil"
              value={meta.unstable}
              variant={meta.unstable > 0 ? "warning" : "default"}
            />
            <StatCard
              label="Offline"
              value={meta.offline}
              variant={meta.offline > 0 ? "warning" : "default"}
            />
          </div>

          <NvrStatus nvrs={nvrsQuery.data ?? []} />

          <div className="bg-card flex flex-col gap-3 rounded-xl border p-4">
            <h3 className="text-sm font-semibold">Kamera Bermasalah ({troubledCameras.length})</h3>
            {troubledCameras.length === 0 ? (
              <p className="text-muted-foreground text-xs">
                Semua kamera di lokasi ini online dan stabil.
              </p>
            ) : (
              <div className="flex max-h-96 flex-col gap-2.5 overflow-y-auto pr-1">
                {troubledCameras.map((cam) => (
                  <div
                    key={cam.id}
                    className="bg-panel flex flex-col gap-2 rounded-lg border p-3 text-xs"
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <div className="font-semibold">{cam.name}</div>
                        <div className="text-muted-foreground text-[11px]">
                          <Mono>{cam.ip}</Mono> · {cam.switch_name} {cam.port}
                        </div>
                      </div>
                      <CameraStatusBadge status={cam.status} />
                    </div>

                    <div className="flex flex-wrap gap-1.5 pt-1">
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => pingCamera.mutate(cam.id)}
                        disabled={pingCamera.isPending}
                      >
                        <Radio className="size-3.5" aria-hidden="true" />
                        Ping Ulang
                      </Button>

                      {canWriteMikrotik && cam.port ? (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() =>
                            confirmToast({
                              title: `Restart PoE untuk ${cam.name}?`,
                              description: `Port ${cam.port} akan dimatikan sesaat lalu dinyalakan ulang.`,
                              confirmLabel: "Restart PoE",
                              onConfirm: () => restartPoe.mutateAsync(cam.port ?? "port 2"),
                            })
                          }
                        >
                          <RotateCcw className="size-3.5" aria-hidden="true" />
                          Restart PoE
                        </Button>
                      ) : null}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </aside>
      </div>
    </div>
  );
}
