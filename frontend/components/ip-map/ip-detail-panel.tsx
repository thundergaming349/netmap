"use client";

import { Lock, Network, Plus, Route, ShieldBan } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { Mono } from "@/components/common/mono";
import { StatusBadge } from "@/components/status/status-badge";
import { Button } from "@/components/ui/button";
import { useCan } from "@/hooks/use-me";
import { useBlockMac, useMakeLeaseStatic } from "@/hooks/use-mikrotik-actions";
import { formatLastSeen, formatMac } from "@/lib/format";
import type { IpMapCell } from "@/types/api";

type IpDetailPanelProps = {
  cell: IpMapCell | null;
  segmentId: number;
  segmentName: string;
  onAddDeviceAtIp?: (ip: string) => void;
};

export function IpDetailPanel({
  cell,
  segmentId,
  segmentName,
  onAddDeviceAtIp,
}: IpDetailPanelProps) {
  const canManageDevices = useCan("manage-devices");
  const canWriteMikrotik = useCan("mikrotik-write");
  const makeStatic = useMakeLeaseStatic();
  const blockMac = useBlockMac();
  const [confirmKind, setConfirmKind] = useState<"static" | "block" | null>(null);

  if (!cell) {
    return (
      <aside className="bg-card flex flex-col justify-center gap-2 rounded-xl border p-6 text-center">
        <p className="text-sm font-semibold">Pilih kotak IP</p>
        <p className="text-muted-foreground text-xs">
          Klik salah satu kotak pada grid .0–.255 untuk melihat perangkat, MAC, lokasi port, atau
          detail konflik.
        </p>
      </aside>
    );
  }

  return (
    <aside className="bg-card flex flex-col gap-4 rounded-xl border p-5">
      <div className="flex items-start justify-between gap-3 border-b pb-3.5">
        <div>
          <span className="text-muted-foreground text-xs">{segmentName}</span>
          <h3 className="font-mono text-xl font-bold">{cell.ip}</h3>
        </div>
        <StatusBadge status={cell.status} label={cell.status_label} />
      </div>

      {cell.conflict ? (
        <div className="border-warn-border bg-warn-bg/60 flex flex-col gap-1.5 rounded-lg border p-3.5 text-xs">
          <p className="text-status-conflict font-semibold">{cell.conflict.title}</p>
          <p className="text-foreground/90 leading-relaxed">{cell.conflict.summary}</p>
          {cell.conflict.suggestion ? (
            <p className="text-muted-foreground pt-1">
              <strong className="text-foreground">Saran:</strong> {cell.conflict.suggestion}
            </p>
          ) : null}
        </div>
      ) : null}

      <dl className="grid grid-cols-2 gap-x-3 gap-y-3 text-xs">
        <div>
          <dt className="text-muted-foreground">Perangkat</dt>
          <dd className="mt-0.5 font-semibold">
            {cell.device?.name ?? cell.lease?.host_name ?? cell.reservation?.label ?? "—"}
          </dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Tipe</dt>
          <dd className="mt-0.5">
            {cell.device?.type_label ??
              (cell.lease ? "DHCP Client" : cell.reservation ? "Reservasi" : "—")}
          </dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Alamat MAC</dt>
          <dd className="mt-0.5">
            <Mono>{formatMac(cell.mac)}</Mono>
          </dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Lokasi / Port</dt>
          <dd className="mt-0.5">
            {cell.device?.port ? <Mono>{cell.device.port}</Mono> : (cell.device?.location ?? "—")}
          </dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Terakhir terlihat</dt>
          <dd className="mt-0.5">{formatLastSeen(cell.last_seen_at)}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Pool DHCP</dt>
          <dd className="mt-0.5">{cell.in_pool ? "Di dalam pool" : "Di luar pool (Static)"}</dd>
        </div>
      </dl>

      {cell.note ? (
        <div className="bg-panel rounded-lg border p-3 text-xs">
          <span className="text-muted-foreground block text-[11px]">Catatan</span>
          <p className="mt-0.5">{cell.note}</p>
        </div>
      ) : null}

      <div className="flex flex-wrap gap-2 border-t pt-3.5">
        {cell.status === "free" || cell.status === "reserved" ? (
          canManageDevices && onAddDeviceAtIp ? (
            <Button size="sm" onClick={() => onAddDeviceAtIp(cell.ip)} className="w-full">
              <Plus className="size-4" aria-hidden="true" />
              Pasang perangkat di {cell.ip}
            </Button>
          ) : null
        ) : null}

        {cell.device ? (
          <Button
            variant="outline"
            size="sm"
            render={<Link href={`/route?device=${cell.device.id}`} />}
          >
            <Route className="size-4" aria-hidden="true" />
            Trace jalur
          </Button>
        ) : null}

        {cell.device?.type === "camera" ? (
          <Button variant="outline" size="sm" render={<Link href={`/cctv?kamera=${cell.octet}`} />}>
            <Network className="size-4" aria-hidden="true" />
            Lihat CCTV
          </Button>
        ) : null}

        {canWriteMikrotik && cell.mac && (cell.status === "dhcp" || cell.status === "risk") ? (
          <Button variant="outline" size="sm" onClick={() => setConfirmKind("static")}>
            <Lock className="size-4" aria-hidden="true" />
            Kunci Lease Static
          </Button>
        ) : null}

        {canWriteMikrotik && cell.mac && cell.status === "conflict" ? (
          <Button
            variant="outline"
            size="sm"
            className="border-warn-border text-warn-fg"
            onClick={() => setConfirmKind("block")}
          >
            <ShieldBan className="size-4" aria-hidden="true" />
            Blokir MAC
          </Button>
        ) : null}
      </div>

      <ConfirmDialog
        open={confirmKind === "static"}
        onOpenChange={(open) => !open && setConfirmKind(null)}
        title="Buat DHCP lease static di MikroTik?"
        description={
          <>
            Alamat <Mono>{cell.ip}</Mono> akan dikunci khusus untuk MAC{" "}
            <Mono>{formatMac(cell.mac)}</Mono> supaya tidak dibagikan ke perangkat lain.
          </>
        }
        confirmLabel="Kunci Lease Static"
        onConfirm={async () => {
          if (!cell.mac) return;
          await makeStatic.mutateAsync({
            segment_id: segmentId,
            ip: cell.ip,
            mac: cell.mac,
            comment: cell.device?.name ?? cell.lease?.host_name ?? `Static-${cell.ip}`,
          });
        }}
      />

      <ConfirmDialog
        open={confirmKind === "block"}
        onOpenChange={(open) => !open && setConfirmKind(null)}
        title="Blokir alamat MAC ini di MikroTik?"
        description={
          <>
            Perangkat dengan MAC <Mono>{formatMac(cell.mac)}</Mono> pada IP <Mono>{cell.ip}</Mono>{" "}
            tidak akan bisa meneruskan trafik di bridge MikroTik.
          </>
        }
        confirmLabel="Blokir MAC"
        destructive
        onConfirm={async () => {
          if (!cell.mac) return;
          await blockMac.mutateAsync({
            segment_id: segmentId,
            ip: cell.ip,
            mac: cell.mac,
            reason: `Konflik IP pada ${cell.ip}`,
          });
        }}
      />
    </aside>
  );
}
