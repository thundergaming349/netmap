"use client";

import { CheckCircle2, Lock, RotateCcw, ShieldBan } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { Mono } from "@/components/common/mono";
import { SeverityBadge } from "@/components/status/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useResolveConflict } from "@/hooks/use-conflicts";
import { useCan } from "@/hooks/use-me";
import { useBlockMac, useMakeLeaseStatic, useRestartPoe } from "@/hooks/use-mikrotik-actions";
import { formatDateTime, formatMac } from "@/lib/format";
import { confirmToast } from "@/lib/toast";
import type { ConflictEvent } from "@/types/api";
import { ConflictTimeline } from "./conflict-timeline";

type ConflictDetailProps = {
  event: ConflictEvent | null;
};

export function ConflictDetail({ event }: ConflictDetailProps) {
  const canResolve = useCan("resolve-conflicts");
  const canWrite = useCan("mikrotik-write");
  const resolveConflict = useResolveConflict();
  const makeStatic = useMakeLeaseStatic();
  const blockMac = useBlockMac();
  const restartPoe = useRestartPoe();

  const [note, setNote] = useState("");
  const [blockingMac, setBlockingMac] = useState<string | null>(null);

  if (!event) {
    return (
      <aside className="bg-card flex flex-col justify-center gap-2 rounded-xl border p-6 text-center">
        <p className="text-sm font-semibold">Pilih kejadian konflik</p>
        <p className="text-muted-foreground text-xs">
          Klik salah satu kejadian di daftar kiri untuk melihat sumber deteksi, kronologi, dan saran
          perbaikan.
        </p>
      </aside>
    );
  }

  const foreignMac =
    event.macs.find((m) => m.toLowerCase() !== event.device?.mac?.toLowerCase()) ?? event.macs[0];

  return (
    <aside className="bg-card flex flex-col gap-4 rounded-xl border p-5">
      <div className="flex flex-wrap items-start justify-between gap-2 border-b pb-3.5">
        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <SeverityBadge severity={event.severity} />
            <span className="text-muted-foreground text-xs">{event.type_label}</span>
          </div>
          <h3 className="text-base font-semibold">{event.title}</h3>
        </div>
        <Link
          href={`/peta-ip?jalur=${event.segment?.slug ?? ""}&ip=${event.ip.split(".").pop()}`}
          className="text-primary font-mono text-xs hover:underline"
        >
          Buka di Peta IP →
        </Link>
      </div>

      <p className="text-sm leading-relaxed">{event.summary}</p>

      <div className="border-info-border bg-info-bg/50 rounded-lg border p-3.5 text-xs">
        <div className="text-info-fg font-semibold">Saran Perbaikan</div>
        <p className="text-foreground/90 mt-1 leading-relaxed">{event.suggestion}</p>
      </div>

      <dl className="grid grid-cols-2 gap-3 text-xs">
        <div>
          <dt className="text-muted-foreground">Alamat IP</dt>
          <dd className="mt-0.5 font-semibold">
            <Mono>{event.ip}</Mono>
          </dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Jalur</dt>
          <dd className="mt-0.5">{event.segment?.name ?? "—"}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Sumber Deteksi</dt>
          <dd className="mt-0.5">
            <Mono>{event.source}</Mono>
          </dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Terdeteksi Sejak</dt>
          <dd className="mt-0.5">{formatDateTime(event.opened_at)}</dd>
        </div>
        <div className="col-span-2">
          <dt className="text-muted-foreground">MAC yang Terlibat</dt>
          <dd className="mt-1 flex flex-wrap gap-1.5">
            {event.macs.map((mac) => (
              <Mono key={mac} className="bg-panel rounded border px-2 py-0.5 text-xs">
                {formatMac(mac)}
              </Mono>
            ))}
          </dd>
        </div>
      </dl>

      {/* Quick Remediation Actions */}
      {event.status === "active" ? (
        <div className="flex flex-col gap-3 border-t pt-3.5">
          <div className="flex flex-wrap gap-2">
            {canWrite && foreignMac && (event.type === "dup_ip" || event.type === "res_misuse") ? (
              <Button
                variant="outline"
                size="sm"
                className="border-warn-border text-warn-fg"
                onClick={() => setBlockingMac(foreignMac)}
              >
                <ShieldBan className="size-4" aria-hidden="true" />
                Blokir MAC <Mono className="ml-1">{formatMac(foreignMac)}</Mono>
              </Button>
            ) : null}

            {canWrite && event.macs[0] && event.type === "static_in_pool" ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() =>
                  confirmToast({
                    title: `Buat DHCP lease static untuk ${event.ip}?`,
                    description: `MAC ${formatMac(event.macs[0])} akan dikunci di MikroTik.`,
                    confirmLabel: "Kunci Lease Static",
                    onConfirm: () =>
                      makeStatic.mutateAsync({
                        segment_id: event.segment_id,
                        ip: event.ip,
                        mac: event.macs[0] ?? "",
                        comment: event.device?.name ?? `Static-${event.ip}`,
                      }),
                  })
                }
              >
                <Lock className="size-4" aria-hidden="true" />
                Buat DHCP Lease Static
              </Button>
            ) : null}

            {canWrite && event.type === "cam_offline" && event.device?.port ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() =>
                  confirmToast({
                    title: `Restart PoE pada ${event.device?.port}?`,
                    description: `Daya kamera ${event.device?.name} akan diputus lalu dinyalakan ulang.`,
                    confirmLabel: "Restart PoE",
                    onConfirm: () => restartPoe.mutateAsync(event.device?.port ?? "ether4"),
                  })
                }
              >
                <RotateCcw className="size-4" aria-hidden="true" />
                Restart PoE ({event.device.port})
              </Button>
            ) : null}
          </div>

          {canResolve ? (
            <div className="flex flex-col gap-2">
              <Input
                value={note}
                onChange={(e) => setNote(e.target.value)}
                placeholder="Catatan penyelesaian (opsional)…"
                aria-label="Catatan penyelesaian konflik"
              />
              <Button
                size="sm"
                onClick={() =>
                  confirmToast({
                    title: `Tandai konflik ${event.ip} selesai?`,
                    description: "Kejadian ini akan dipindahkan ke tab Selesai.",
                    confirmLabel: "Tandai Selesai",
                    onConfirm: async () => {
                      await resolveConflict.mutateAsync({ id: event.id, note });
                      setNote("");
                    },
                  })
                }
              >
                <CheckCircle2 className="size-4" aria-hidden="true" />
                Tandai Selesai
              </Button>
            </div>
          ) : null}
        </div>
      ) : (
        <div className="bg-panel rounded-lg border p-3 text-xs">
          <span className="text-muted-foreground block">Diselesaikan pada</span>
          <p className="mt-0.5 font-medium">
            {formatDateTime(event.resolved_at)}
            {event.resolved_by_name ? ` oleh ${event.resolved_by_name}` : ""}
          </p>
          {event.resolution_note ? (
            <p className="text-muted-foreground mt-1">{event.resolution_note}</p>
          ) : null}
        </div>
      )}

      <div className="flex flex-col gap-2.5 border-t pt-3.5">
        <h4 className="text-xs font-semibold uppercase">Kronologi Kejadian</h4>
        <ConflictTimeline logs={event.logs ?? []} />
      </div>

      <ConfirmDialog
        open={blockingMac !== null}
        onOpenChange={(open) => !open && setBlockingMac(null)}
        title="Blokir MAC di Bridge Filter MikroTik?"
        description={
          <>
            Perangkat dengan alamat MAC <Mono>{formatMac(blockingMac)}</Mono> pada IP{" "}
            <Mono>{event.ip}</Mono> tidak akan bisa terhubung ke jaringan.
          </>
        }
        confirmLabel="Blokir MAC"
        destructive
        onConfirm={async () => {
          if (!blockingMac) return;
          await blockMac.mutateAsync({
            segment_id: event.segment_id,
            ip: event.ip,
            mac: blockingMac,
            reason: `Konflik ${event.type_label} pada ${event.ip}`,
          });
        }}
      />
    </aside>
  );
}
