"use client";

import { ArrowRight, HardDrive, Laptop, Network, Router, Wifi } from "lucide-react";
import { Mono } from "@/components/common/mono";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { useDevices, useDeviceTrace } from "@/hooks/use-devices";
import { formatMac } from "@/lib/format";
import { cn } from "@/lib/utils";
import type { TraceHop } from "@/types/api";

type TraceTimelineProps = {
  selectedDeviceId: number | null;
  onSelectDevice: (deviceId: number) => void;
};

export function TraceTimeline({ selectedDeviceId, onSelectDevice }: TraceTimelineProps) {
  const devices = useDevices();
  const activeId = selectedDeviceId ?? devices.data?.[0]?.id ?? null;
  const trace = useDeviceTrace(activeId);

  const deviceItems = (devices.data ?? []).map((d) => ({
    value: String(d.id),
    label: `${d.name} (${d.ip})`,
  }));

  return (
    <div className="bg-card flex flex-col gap-4 rounded-xl border p-4 md:p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold">Trace Jalur Perangkat</h2>
          <p className="text-muted-foreground text-xs">
            Urutan hop fisik dan logis dari perangkat ke switch, router MikroTik, NVR, hingga uplink
            ISP.
          </p>
        </div>

        {deviceItems.length > 0 ? (
          <div className="min-w-[260px]">
            <Select
              items={deviceItems}
              value={activeId ? String(activeId) : undefined}
              onValueChange={(val) => {
                if (val) onSelectDevice(Number(val));
              }}
            >
              <SelectTrigger aria-label="Pilih perangkat untuk di-trace" className="w-full">
                <SelectValue placeholder="Pilih perangkat…" />
              </SelectTrigger>
              <SelectContent>
                {deviceItems.map((item) => (
                  <SelectItem key={item.value} value={item.value}>
                    {item.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        ) : null}
      </div>

      {trace.isPending ? (
        <div className="grid gap-3 sm:grid-cols-4">
          {Array.from({ length: 4 }, (_, i) => (
            <Skeleton key={i} className="h-24 w-full" />
          ))}
        </div>
      ) : null}

      {trace.data ? (
        <div className="flex flex-wrap items-stretch gap-2.5">
          {trace.data.hops.map((hop, index) => (
            <div key={hop.step} className="flex min-w-[200px] flex-1 items-center gap-2.5">
              <HopCard hop={hop} />
              {index < trace.data.hops.length - 1 ? (
                <ArrowRight
                  className="text-muted-foreground hidden size-4 shrink-0 lg:block"
                  aria-hidden="true"
                />
              ) : null}
            </div>
          ))}
        </div>
      ) : null}
    </div>
  );
}

function HopCard({ hop }: { hop: TraceHop }) {
  const Icon =
    hop.kind === "router"
      ? Router
      : hop.kind === "switch"
        ? Network
        : hop.kind === "nvr"
          ? HardDrive
          : hop.kind === "uplink"
            ? Wifi
            : Laptop;

  return (
    <div className="bg-panel flex flex-1 flex-col justify-between gap-2 rounded-xl border p-3.5 text-xs">
      <div className="flex items-center justify-between gap-2">
        <span className="bg-secondary text-primary inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 font-mono text-[11px] font-bold">
          <Icon className="size-3.5" aria-hidden="true" />
          Hop {hop.step}
        </span>
        <span
          className={cn(
            "rounded px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase",
            hop.status === "online"
              ? "bg-status-online-bg text-status-online-fg"
              : hop.status === "offline"
                ? "bg-status-offline text-status-offline-fg"
                : "bg-status-conflict text-status-conflict-fg",
          )}
        >
          {hop.latency_ms ? `${hop.latency_ms} ms` : hop.status}
        </span>
      </div>

      <div>
        <div className="text-sm font-semibold">{hop.name}</div>
        <div className="text-muted-foreground text-[11px]">{hop.subtitle}</div>
      </div>

      <div className="text-muted-foreground flex flex-col gap-0.5 border-t pt-2 text-[11px]">
        {hop.ip ? (
          <div>
            IP: <Mono className="text-foreground">{hop.ip}</Mono>
          </div>
        ) : null}
        {hop.mac ? (
          <div>
            MAC: <Mono className="text-foreground">{formatMac(hop.mac)}</Mono>
          </div>
        ) : null}
        {hop.port ? (
          <div>
            Port: <Mono className="text-foreground">{hop.port}</Mono>
          </div>
        ) : null}
      </div>
    </div>
  );
}
