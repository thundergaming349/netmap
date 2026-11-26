"use client";

import { Download, Plus, Radar, Sparkles, Trash2 } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { ErrorState } from "@/components/common/error-state";
import { Mono } from "@/components/common/mono";
import { PageHeader } from "@/components/common/page-header";
import { DeviceForm } from "@/components/device/device-form";
import { DeviceTable } from "@/components/device/device-table";
import { SegmentTabs } from "@/components/segment/segment-tabs";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { useDevices } from "@/hooks/use-devices";
import { useCan } from "@/hooks/use-me";
import { useScanArp } from "@/hooks/use-mikrotik-actions";
import {
  useCreateReservation,
  useDeleteReservation,
  useIpMap,
  useReservations,
  useSegments,
} from "@/hooks/use-segments";
import { confirmToast } from "@/lib/toast";
import type { IpMapCell, Segment } from "@/types/api";
import { IpChecker } from "./ip-checker";
import { IpDetailPanel } from "./ip-detail-panel";
import { IpGrid } from "./ip-grid";

export function IpMapView() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const segmentsQuery = useSegments();
  const scanArp = useScanArp();
  const canManageDevices = useCan("manage-devices");
  const canManageReservations = useCan("manage-reservations");

  const slugParam = searchParams.get("jalur");
  const ipParam = searchParams.get("ip");

  const segments = segmentsQuery.data ?? [];
  const activeSegment: Segment | null =
    segments.find((s) => s.slug === slugParam) ?? segments[0] ?? null;

  const ipMap = useIpMap(activeSegment?.id ?? null);
  const segmentDevices = useDevices({ segment_id: activeSegment?.id ?? null });
  const reservations = useReservations(activeSegment?.id ?? null);
  const createReservation = useCreateReservation();
  const deleteReservation = useDeleteReservation();

  const [selectedOctet, setSelectedOctet] = useState<number | null>(
    ipParam ? Number.parseInt(ipParam, 10) : 31,
  );
  const [addingAtIp, setAddingAtIp] = useState<string | null>(null);
  const [showReservationForm, setShowReservationForm] = useState(false);
  const [resStart, setResStart] = useState("80");
  const [resEnd, setResEnd] = useState("89");
  const [resLabel, setResLabel] = useState("");
  const [resDesc, setResDesc] = useState("");

  function handleSelectSegment(seg: Segment) {
    const next = new URLSearchParams(searchParams.toString());
    next.set("jalur", seg.slug);
    next.delete("ip");
    router.replace(`/peta-ip?${next.toString()}`, { scroll: false });
    setSelectedOctet(null);
  }

  function handleSelectCell(cell: IpMapCell) {
    setSelectedOctet(cell.octet);
    const next = new URLSearchParams(searchParams.toString());
    if (activeSegment) next.set("jalur", activeSegment.slug);
    next.set("ip", String(cell.octet));
    router.replace(`/peta-ip?${next.toString()}`, { scroll: false });
  }

  async function handleAddReservation(e: React.FormEvent) {
    e.preventDefault();
    if (!activeSegment) return;

    await createReservation.mutateAsync({
      segment_id: activeSegment.id,
      start_octet: Number.parseInt(resStart, 10),
      end_octet: Number.parseInt(resEnd, 10),
      label: resLabel,
      description: resDesc || undefined,
    });
    setResLabel("");
    setResDesc("");
    setShowReservationForm(false);
  }

  if (segmentsQuery.isPending) {
    return (
      <div className="flex flex-col gap-5">
        <Skeleton className="h-12 w-72" />
        <Skeleton className="h-96 w-full" />
      </div>
    );
  }

  if (segmentsQuery.isError || !activeSegment) {
    return (
      <ErrorState
        message={segmentsQuery.error?.message}
        onRetry={() => void segmentsQuery.refetch()}
      />
    );
  }

  const cells = ipMap.data?.cells ?? [];
  const selectedCell =
    cells.find((c) => c.octet === selectedOctet) ??
    cells.find((c) => c.status === "conflict") ??
    cells[1] ??
    null;

  const prefix = activeSegment.subnet.split("/")[0]?.split(".").slice(0, 3).join(".");

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <PageHeader
          title="Peta IP per Jalur"
          description="Grid 256 alamat IP (.0–.255), cek ketersediaan IP sebelum dipasang, dan daftar reservasi per VLAN."
        />
        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            onClick={() => scanArp.mutate(activeSegment.id)}
            disabled={scanArp.isPending}
          >
            <Radar className="size-4" aria-hidden="true" />
            {scanArp.isPending ? "Memindai…" : `Scan ARP ${activeSegment.name}`}
          </Button>
          {canManageDevices ? (
            <Button
              onClick={() =>
                setAddingAtIp(ipMap.data?.summary.next_free_ip ?? activeSegment.next_free_ip ?? "")
              }
            >
              <Plus className="size-4" aria-hidden="true" />
              Tambah Perangkat
            </Button>
          ) : null}
        </div>
      </div>

      <SegmentTabs
        segments={segments}
        selectedId={activeSegment.id}
        onSelect={handleSelectSegment}
      />

      {/* Segment Info Strip */}
      <div className="bg-card flex flex-wrap items-center justify-between gap-4 rounded-xl border px-5 py-3.5 text-xs">
        <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
          <div>
            <span className="text-muted-foreground block text-[11px]">Subnet</span>
            <Mono className="font-semibold">{activeSegment.subnet}</Mono>
          </div>
          <div>
            <span className="text-muted-foreground block text-[11px]">Gateway</span>
            <Mono className="font-semibold">{activeSegment.gateway}</Mono>
          </div>
          <div>
            <span className="text-muted-foreground block text-[11px]">VLAN &amp; Port</span>
            <Mono className="font-semibold">
              {activeSegment.vlan_id ? `VLAN ${activeSegment.vlan_id} · ` : ""}
              {activeSegment.interface}
            </Mono>
          </div>
          <div>
            <span className="text-muted-foreground block text-[11px]">Pool DHCP</span>
            <Mono className="font-semibold">
              {activeSegment.pool_start && activeSegment.pool_end
                ? `.${activeSegment.pool_start.split(".").pop()}–.${activeSegment.pool_end.split(".").pop()}`
                : "Semua Static (Tanpa DHCP)"}
            </Mono>
          </div>
          <div>
            <span className="text-muted-foreground block text-[11px]">Range Static</span>
            <Mono className="font-semibold">
              .{activeSegment.static_start}–.{activeSegment.static_end}
            </Mono>
          </div>
          <div>
            <span className="text-muted-foreground block text-[11px]">Terpakai</span>
            <Mono className="font-semibold">
              {ipMap.data?.summary.used_count ?? activeSegment.used_ips} / 254 IP
            </Mono>
          </div>
        </div>

        {ipMap.data?.summary.next_free_ip ? (
          <div className="bg-panel flex items-center gap-3 rounded-lg border px-3.5 py-2">
            <div>
              <span className="text-muted-foreground block text-[10px] uppercase">
                IP Kosong Berikutnya
              </span>
              <Mono className="text-primary text-sm font-bold">
                {ipMap.data.summary.next_free_ip}
              </Mono>
            </div>
            {canManageDevices ? (
              <Button
                size="sm"
                variant="outline"
                onClick={() => setAddingAtIp(ipMap.data?.summary.next_free_ip ?? "")}
              >
                <Sparkles className="size-3.5" aria-hidden="true" />
                Pakai IP ini
              </Button>
            ) : null}
          </div>
        ) : null}
      </div>

      {/* Add Device Modal / Inline Drawer when triggered */}
      {addingAtIp !== null ? (
        <div className="bg-card border-primary/50 flex flex-col gap-4 rounded-xl border p-5">
          <div className="flex items-center justify-between border-b pb-3">
            <h3 className="text-base font-semibold">
              Tambah Perangkat di Jalur {activeSegment.name}
            </h3>
            <Button variant="ghost" size="sm" onClick={() => setAddingAtIp(null)}>
              Tutup
            </Button>
          </div>
          <DeviceForm
            segments={segments}
            initialSegmentId={activeSegment.id}
            initialIp={addingAtIp}
            onSuccess={() => setAddingAtIp(null)}
            onCancel={() => setAddingAtIp(null)}
          />
        </div>
      ) : null}

      {/* Main 2-column layout: 16x16 Grid + Detail Panel & IP Checker */}
      <div className="flex flex-wrap items-start gap-5">
        <div className="flex flex-col gap-5" style={{ flex: "999 1 560px" }}>
          {ipMap.isPending ? (
            <Skeleton className="h-[520px] w-full" />
          ) : (
            <IpGrid
              cells={cells}
              selectedOctet={selectedCell?.octet ?? null}
              onSelectCell={handleSelectCell}
            />
          )}

          {/* Reservations Section */}
          <section className="bg-card flex flex-col gap-4 rounded-xl border p-5">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h2 className="text-base font-semibold">
                  Reservasi Range IP — Jalur {activeSegment.name}
                </h2>
                <p className="text-muted-foreground text-xs">
                  Range IP yang dicadangkan untuk ekspansi agar tidak dipakai perangkat lain.
                </p>
              </div>
              {canManageReservations ? (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setShowReservationForm((v) => !v)}
                >
                  <Plus className="size-4" aria-hidden="true" />
                  Tambah Reservasi
                </Button>
              ) : null}
            </div>

            {showReservationForm ? (
              <form
                onSubmit={(e) => void handleAddReservation(e)}
                className="bg-panel grid gap-3 rounded-xl border p-4 sm:grid-cols-4"
              >
                <div className="flex flex-col gap-1">
                  <Label htmlFor="res-start">Oktet Awal (.2–.254)</Label>
                  <Input
                    id="res-start"
                    type="number"
                    min={2}
                    max={254}
                    value={resStart}
                    onChange={(e) => setResStart(e.target.value)}
                    className="font-mono"
                    required
                  />
                </div>
                <div className="flex flex-col gap-1">
                  <Label htmlFor="res-end">Oktet Akhir (.2–.254)</Label>
                  <Input
                    id="res-end"
                    type="number"
                    min={2}
                    max={254}
                    value={resEnd}
                    onChange={(e) => setResEnd(e.target.value)}
                    className="font-mono"
                    required
                  />
                </div>
                <div className="flex flex-col gap-1 sm:col-span-2">
                  <Label htmlFor="res-label">Keperluan Reservasi</Label>
                  <Input
                    id="res-label"
                    value={resLabel}
                    onChange={(e) => setResLabel(e.target.value)}
                    placeholder="Contoh: Kamera Gedung B"
                    required
                  />
                </div>
                <div className="flex flex-col gap-1 sm:col-span-3">
                  <Label htmlFor="res-desc">Catatan (opsional)</Label>
                  <Input
                    id="res-desc"
                    value={resDesc}
                    onChange={(e) => setResDesc(e.target.value)}
                    placeholder="Keterangan tambahan"
                  />
                </div>
                <div className="flex items-end justify-end gap-2">
                  <Button type="submit" size="sm" disabled={createReservation.isPending}>
                    Simpan
                  </Button>
                </div>
              </form>
            ) : null}

            <div className="grid gap-2.5 sm:grid-cols-2">
              {(reservations.data ?? []).map((res) => (
                <div
                  key={res.id}
                  className="bg-panel flex items-start justify-between gap-3 rounded-lg border p-3.5 text-xs"
                >
                  <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-2">
                      <span className="bg-status-reserved text-status-reserved-fg rounded px-1.5 py-0.5 font-mono text-[11px] font-bold">
                        {res.range_label}
                      </span>
                      <span className="font-semibold">{res.label}</span>
                    </div>
                    <div className="text-muted-foreground text-[11px]">
                      <Mono>
                        {res.ip_start} – {res.ip_end}
                      </Mono>{" "}
                      ({res.count} IP)
                    </div>
                    {res.description ? (
                      <p className="text-muted-foreground">{res.description}</p>
                    ) : null}
                  </div>
                  {canManageReservations ? (
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      aria-label={`Hapus reservasi ${res.label}`}
                      onClick={() =>
                        confirmToast({
                          title: `Hapus reservasi ${res.label} (${res.range_label})?`,
                          confirmLabel: "Hapus",
                          onConfirm: () => deleteReservation.mutateAsync(res.id),
                        })
                      }
                    >
                      <Trash2 className="text-destructive size-4" />
                    </Button>
                  ) : null}
                </div>
              ))}
            </div>
          </section>
        </div>

        {/* Right Side Column: IP Detail Panel + IP Checker */}
        <div className="flex flex-col gap-5" style={{ flex: "1 1 320px" }}>
          <IpDetailPanel
            cell={selectedCell}
            segmentId={activeSegment.id}
            segmentName={activeSegment.name}
            onAddDeviceAtIp={canManageDevices ? (ip) => setAddingAtIp(ip) : undefined}
          />

          <IpChecker
            segmentId={activeSegment.id}
            defaultPrefix={prefix}
            onUseSuggestedIp={canManageDevices ? (ip) => setAddingAtIp(ip) : undefined}
          />
        </div>
      </div>

      {/* Static Devices in this Segment */}
      <section className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 className="text-base font-semibold">
              Perangkat Terdaftar di Jalur {activeSegment.name}
            </h2>
            <p className="text-muted-foreground text-xs">
              Daftar perangkat static dan lease static yang tercatat di jalur ini.
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            render={<a href={`/api/v1/devices/export?segment_id=${activeSegment.id}`} download />}
          >
            <Download className="size-4" aria-hidden="true" />
            Ekspor CSV
          </Button>
        </div>

        <DeviceTable devices={segmentDevices.data} isLoading={segmentDevices.isPending} />
      </section>
    </div>
  );
}
