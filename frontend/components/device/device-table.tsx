"use client";

import { Pencil, Route, Trash2 } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { ConfirmDialog } from "@/components/common/confirm-dialog";
import { EmptyState } from "@/components/common/empty-state";
import { Mono } from "@/components/common/mono";
import { SegmentDot } from "@/components/segment/segment-dot";
import { StatusBadge } from "@/components/status/status-badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useDeleteDevice } from "@/hooks/use-devices";
import { useCan } from "@/hooks/use-me";
import { formatLastSeen, formatMac } from "@/lib/format";
import type { Device } from "@/types/api";

type DeviceTableProps = {
  devices: Device[] | undefined;
  isLoading: boolean;
  onEdit?: (device: Device) => void;
};

export function DeviceTable({ devices, isLoading, onEdit }: DeviceTableProps) {
  const canManage = useCan("manage-devices");
  const deleteDevice = useDeleteDevice();
  const [deleting, setDeleting] = useState<Device | null>(null);

  if (isLoading) {
    return (
      <div className="bg-card flex flex-col gap-2 rounded-xl border p-4">
        {Array.from({ length: 6 }, (_, i) => (
          <Skeleton key={i} className="h-10 w-full" />
        ))}
      </div>
    );
  }

  if (!devices || devices.length === 0) {
    return (
      <EmptyState
        title="Belum ada perangkat yang cocok"
        description="Tambah perangkat baru atau ubah kata kunci pencarian dan filter jalur."
      />
    );
  }

  return (
    <div className="bg-card overflow-hidden rounded-xl border">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[860px] border-collapse text-[13px]">
          <thead>
            <tr className="text-muted-foreground border-b text-left text-xs">
              <th className="px-4 py-3 font-medium">Perangkat</th>
              <th className="px-3 py-3 font-medium">Tipe</th>
              <th className="px-3 py-3 font-medium">Alamat IP</th>
              <th className="px-3 py-3 font-medium">Jalur</th>
              <th className="px-3 py-3 font-medium">MAC</th>
              <th className="px-3 py-3 font-medium">Lokasi / Port</th>
              <th className="px-3 py-3 font-medium">Status</th>
              <th className="px-3 py-3 font-medium">Terakhir terlihat</th>
              <th className="px-4 py-3 text-right font-medium">Aksi</th>
            </tr>
          </thead>
          <tbody>
            {devices.map((device) => (
              <tr key={device.id} className="hover:bg-panel/60 border-t transition-colors">
                <td className="px-4 py-3">
                  <div className="font-semibold">{device.name}</div>
                  {device.note ? (
                    <div className="text-muted-foreground max-w-xs truncate text-xs">
                      {device.note}
                    </div>
                  ) : null}
                </td>
                <td className="text-muted-foreground px-3 py-3">{device.type_label}</td>
                <td className="px-3 py-3">
                  <Link
                    href={`/peta-ip?jalur=${device.segment?.slug ?? ""}&ip=${device.ip.split(".").pop()}`}
                    className="text-primary hover:underline"
                  >
                    <Mono className="font-semibold">{device.ip}</Mono>
                  </Link>
                </td>
                <td className="px-3 py-3">
                  {device.segment ? (
                    <span className="inline-flex items-center gap-2">
                      <SegmentDot color={device.segment.color} />
                      <span>{device.segment.name}</span>
                    </span>
                  ) : (
                    "—"
                  )}
                </td>
                <td className="px-3 py-3">
                  <Mono className="text-xs">{formatMac(device.mac)}</Mono>
                </td>
                <td className="px-3 py-3">
                  <div className="text-xs">
                    {device.switch_name ? (
                      <Mono>
                        {device.switch_name} {device.port ?? ""}
                      </Mono>
                    ) : (
                      (device.port ?? device.location ?? "—")
                    )}
                  </div>
                  {device.location && device.switch_name ? (
                    <div className="text-muted-foreground text-[11px]">{device.location}</div>
                  ) : null}
                </td>
                <td className="px-3 py-3">
                  <StatusBadge status={device.ip_status} label={device.ip_status_label} />
                </td>
                <td className="text-muted-foreground px-3 py-3 whitespace-nowrap">
                  {formatLastSeen(device.last_seen_at)}
                </td>
                <td className="px-4 py-3 text-right">
                  <div className="flex items-center justify-end gap-1">
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      aria-label={`Trace jalur ${device.name}`}
                      render={<Link href={`/route?device=${device.id}`} />}
                    >
                      <Route className="size-4" />
                    </Button>
                    {canManage && onEdit ? (
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={`Edit ${device.name}`}
                        onClick={() => onEdit(device)}
                      >
                        <Pencil className="size-4" />
                      </Button>
                    ) : null}
                    {canManage ? (
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={`Hapus ${device.name}`}
                        onClick={() => setDeleting(device)}
                      >
                        <Trash2 className="text-destructive size-4" />
                      </Button>
                    ) : null}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={`Hapus perangkat ${deleting?.name ?? ""}?`}
        description={
          <>
            Perangkat dengan IP <Mono>{deleting?.ip}</Mono> dan MAC{" "}
            <Mono>{formatMac(deleting?.mac)}</Mono> akan dihapus dari daftar inventaris NetMap.
          </>
        }
        confirmLabel="Hapus perangkat"
        destructive
        onConfirm={async () => {
          if (!deleting) return;
          await deleteDevice.mutateAsync(deleting.id);
        }}
      />
    </div>
  );
}
