"use client";

import { CheckCircle2, Sparkles, TriangleAlert } from "lucide-react";
import { useEffect, useState } from "react";
import { Mono } from "@/components/common/mono";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useCreateDevice, useUpdateDevice } from "@/hooks/use-devices";
import { useIpCheck } from "@/hooks/use-segments";
import { ApiError } from "@/lib/api";
import { DEVICE_TYPE_OPTIONS } from "@/lib/status";
import { notify } from "@/lib/toast";
import type { Device, DeviceTypeCode, Segment } from "@/types/api";

type DeviceFormProps = {
  segments: Segment[];
  initialSegmentId?: number | null;
  initialIp?: string;
  editingDevice?: Device | null;
  onSuccess?: () => void;
  onCancel?: () => void;
};

export function DeviceForm({
  segments,
  initialSegmentId,
  initialIp = "",
  editingDevice = null,
  onSuccess,
  onCancel,
}: DeviceFormProps) {
  const defaultSegment =
    segments.find((s) => s.id === (editingDevice?.segment_id ?? initialSegmentId)) ?? segments[0];

  const [name, setName] = useState(editingDevice?.name ?? "");
  const [type, setType] = useState<DeviceTypeCode>(editingDevice?.type ?? "camera");
  const [segmentId, setSegmentId] = useState<number>(defaultSegment?.id ?? 1);
  const [ip, setIp] = useState(editingDevice?.ip ?? initialIp);
  const [mac, setMac] = useState(editingDevice?.mac?.toUpperCase() ?? "");
  const [location, setLocation] = useState(editingDevice?.location ?? "");
  const [port, setPort] = useState(editingDevice?.port ?? "");
  const [note, setNote] = useState(editingDevice?.note ?? "");
  const [monitorPing, setMonitorPing] = useState(editingDevice?.monitor_ping ?? true);
  const [isStaticLease, setIsStaticLease] = useState(editingDevice?.is_static_lease ?? true);
  const [isArpPinned, setIsArpPinned] = useState(editingDevice?.is_arp_pinned ?? false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const selectedSegment = segments.find((s) => s.id === segmentId) ?? defaultSegment;

  const [debouncedIp, setDebouncedIp] = useState(ip);
  useEffect(() => {
    const t = window.setTimeout(() => setDebouncedIp(ip.trim()), 300);
    return () => window.clearTimeout(t);
  }, [ip]);

  const ipCheck = useIpCheck(
    editingDevice && editingDevice.ip === debouncedIp ? "" : debouncedIp,
    segmentId,
  );

  const createDevice = useCreateDevice();
  const updateDevice = useUpdateDevice();
  const isSubmitting = createDevice.isPending || updateDevice.isPending;

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setFieldErrors({});

    try {
      if (editingDevice) {
        await updateDevice.mutateAsync({
          id: editingDevice.id,
          name,
          type,
          segment_id: segmentId,
          ip,
          mac: mac || null,
          location: location || null,
          port: port || null,
          note: note || null,
          monitor_ping: monitorPing,
          is_static_lease: isStaticLease,
          is_arp_pinned: isArpPinned,
        });
      } else {
        await createDevice.mutateAsync({
          name,
          type,
          segment_id: segmentId,
          ip,
          mac: mac || null,
          location: location || null,
          port: port || null,
          note: note || null,
          monitor_ping: monitorPing,
          is_static_lease: isStaticLease,
          is_arp_pinned: isArpPinned,
        });
      }
      onSuccess?.();
    } catch (error) {
      if (error instanceof ApiError) {
        const nextErrors: Record<string, string> = {};
        for (const [key, messages] of Object.entries(error.errors)) {
          if (messages[0]) {
            nextErrors[key] = messages[0];
          }
        }
        if (error.status === 409) {
          nextErrors.ip = error.message;
        }
        setFieldErrors(nextErrors);
        notify.error(error.message);
      }
    }
  }

  const segmentItems = segments.map((s) => ({
    value: String(s.id),
    label: `${s.name} (${s.subnet})`,
  }));

  return (
    <form onSubmit={(e) => void handleSubmit(e)} className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="device-name">Nama perangkat</Label>
          <Input
            id="device-name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Contoh: Cam-Gudang-05"
            required
          />
          {fieldErrors.name ? (
            <span className="text-destructive text-xs">{fieldErrors.name}</span>
          ) : null}
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="device-type">Tipe perangkat</Label>
          <Select
            items={DEVICE_TYPE_OPTIONS}
            value={type}
            onValueChange={(val) => {
              if (val) setType(val as DeviceTypeCode);
            }}
          >
            <SelectTrigger id="device-type" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {DEVICE_TYPE_OPTIONS.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="device-segment">Jalur (VLAN)</Label>
          <Select
            items={segmentItems}
            value={String(segmentId)}
            onValueChange={(val) => {
              if (!val) return;
              const nextId = Number(val);
              setSegmentId(nextId);
              const nextSeg = segments.find((s) => s.id === nextId);
              if (nextSeg?.next_free_ip && !editingDevice) {
                setIp(nextSeg.next_free_ip);
              }
            }}
          >
            <SelectTrigger id="device-segment" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {segmentItems.map((item) => (
                <SelectItem key={item.value} value={item.value}>
                  {item.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-1.5">
          <div className="flex items-center justify-between">
            <Label htmlFor="device-ip">Alamat IP</Label>
            {selectedSegment?.next_free_ip ? (
              <button
                type="button"
                onClick={() => setIp(selectedSegment.next_free_ip ?? "")}
                className="text-primary inline-flex items-center gap-1 text-xs hover:underline"
              >
                <Sparkles className="size-3" aria-hidden="true" />
                Kosong: <Mono>{selectedSegment.next_free_ip}</Mono>
              </button>
            ) : null}
          </div>
          <Input
            id="device-ip"
            value={ip}
            onChange={(e) => setIp(e.target.value)}
            placeholder={selectedSegment?.next_free_ip ?? "192.168.20.50"}
            className="font-mono"
            required
          />
          {fieldErrors.ip ? (
            <span className="text-destructive text-xs">{fieldErrors.ip}</span>
          ) : null}
          {ipCheck.data ? (
            <div className="flex items-center justify-between gap-2 text-xs">
              <span
                className={
                  ipCheck.data.safe
                    ? "text-status-online inline-flex items-center gap-1"
                    : "text-status-conflict inline-flex items-center gap-1"
                }
              >
                {ipCheck.data.safe ? (
                  <CheckCircle2 className="size-3.5" aria-hidden="true" />
                ) : (
                  <TriangleAlert className="size-3.5" aria-hidden="true" />
                )}
                {ipCheck.data.title}
              </span>
              {!ipCheck.data.safe && ipCheck.data.suggestion ? (
                <button
                  type="button"
                  onClick={() => setIp(ipCheck.data.suggestion ?? "")}
                  className="text-primary hover:underline"
                >
                  Pakai <Mono>{ipCheck.data.suggestion}</Mono>
                </button>
              ) : null}
            </div>
          ) : null}
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="device-mac">Alamat MAC</Label>
          <Input
            id="device-mac"
            value={mac}
            onChange={(e) => setMac(e.target.value)}
            placeholder="BC:32:5F:1A:0C:50"
            className="font-mono"
          />
          {fieldErrors.mac ? (
            <span className="text-destructive text-xs">{fieldErrors.mac}</span>
          ) : null}
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="device-location">Lokasi</Label>
          <Input
            id="device-location"
            value={location}
            onChange={(e) => setLocation(e.target.value)}
            placeholder="Contoh: Gudang / Gedung A"
          />
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="device-port">Port Switch / Router</Label>
          <Input
            id="device-port"
            value={port}
            onChange={(e) => setPort(e.target.value)}
            placeholder="Contoh: port 9"
            className="font-mono"
          />
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="device-note">Catatan</Label>
          <Input
            id="device-note"
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Keterangan tambahan (opsional)"
          />
        </div>
      </div>

      <div className="bg-panel flex flex-wrap gap-5 rounded-lg border p-3.5 text-xs">
        <label className="flex cursor-pointer items-center gap-2">
          <input
            type="checkbox"
            checked={monitorPing}
            onChange={(e) => setMonitorPing(e.target.checked)}
            className="size-4 rounded"
          />
          <span>Pantau ping otomatis (fping 30 dtk)</span>
        </label>
        <label className="flex cursor-pointer items-center gap-2">
          <input
            type="checkbox"
            checked={isStaticLease}
            onChange={(e) => setIsStaticLease(e.target.checked)}
            className="size-4 rounded"
          />
          <span>Kunci DHCP lease static</span>
        </label>
        <label className="flex cursor-pointer items-center gap-2">
          <input
            type="checkbox"
            checked={isArpPinned}
            onChange={(e) => setIsArpPinned(e.target.checked)}
            className="size-4 rounded"
          />
          <span>Pasang ARP static</span>
        </label>
      </div>

      <div className="flex items-center justify-end gap-2 pt-1">
        {onCancel ? (
          <Button type="button" variant="outline" onClick={onCancel}>
            Batal
          </Button>
        ) : null}
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? "Menyimpan…" : editingDevice ? "Simpan Perubahan" : "Simpan Perangkat"}
        </Button>
      </div>
    </form>
  );
}
