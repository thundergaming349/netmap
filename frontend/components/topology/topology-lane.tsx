"use client";

import { ArrowRight, Globe, Network, Router as RouterIcon, Shield } from "lucide-react";
import Link from "next/link";
import { Mono } from "@/components/common/mono";
import { SegmentDot } from "@/components/segment/segment-dot";
import { cn } from "@/lib/utils";
import type { TopologyData, TopologyLaneData } from "@/types/api";

type TopologyLaneProps = {
  topology: TopologyData;
  focusedSegmentId: number | null;
};

export function TopologyLane({ topology, focusedSegmentId }: TopologyLaneProps) {
  return (
    <div className="bg-card flex flex-col gap-5 rounded-xl border p-4 md:p-5">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-base font-semibold">Topologi Alur Jaringan per Jalur</h2>
          <p className="text-muted-foreground text-xs">
            Urutan koneksi dari Internet → Uplink ISP → Router MikroTik → Port VLAN → Switch →
            Perangkat.
          </p>
        </div>
      </div>

      {/* Top row: Internet -> Uplinks -> MikroTik Core */}
      <div className="bg-panel flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4">
        <div className="flex items-center gap-3">
          <div className="bg-secondary flex size-10 items-center justify-center rounded-lg border">
            <Globe className="text-primary size-5" aria-hidden="true" />
          </div>
          <div>
            <div className="text-sm font-semibold">Internet</div>
            <div className="text-muted-foreground text-xs">Dual-WAN Failover</div>
          </div>
        </div>

        <ArrowRight className="text-muted-foreground hidden size-4 sm:block" aria-hidden="true" />

        <div className="flex flex-wrap gap-2.5">
          {topology.uplinks.map((uplink) => (
            <div
              key={uplink.id}
              className="bg-card flex items-center gap-3 rounded-lg border px-3.5 py-2 text-xs"
            >
              <span
                className={cn(
                  "size-2.5 rounded-full",
                  uplink.status === "up"
                    ? "bg-status-online"
                    : uplink.status === "standby"
                      ? "bg-status-risk"
                      : "bg-status-conflict",
                )}
              />
              <div>
                <div className="font-semibold">
                  {uplink.name}{" "}
                  <span className="text-muted-foreground font-normal">
                    ({uplink.provider ?? uplink.interface})
                  </span>
                </div>
                <div className="text-muted-foreground flex items-center gap-2">
                  <Mono>{uplink.interface}</Mono>
                  <span>·</span>
                  <span>distance {uplink.distance}</span>
                  {uplink.latency_ms ? (
                    <>
                      <span>·</span>
                      <Mono>{uplink.latency_ms} ms</Mono>
                    </>
                  ) : null}
                </div>
              </div>
            </div>
          ))}
        </div>

        <ArrowRight className="text-muted-foreground hidden size-4 sm:block" aria-hidden="true" />

        <div className="bg-card border-primary/40 flex items-center gap-3 rounded-lg border px-4 py-2.5">
          <RouterIcon className="text-primary size-5" aria-hidden="true" />
          <div>
            <div className="text-sm font-semibold">{topology.router?.name ?? "MikroTik-Core"}</div>
            <div className="text-muted-foreground text-xs">
              <Mono>{topology.router?.host ?? "10.10.99.1"}</Mono> · RouterOS{" "}
              {topology.router?.version ?? "v7"}
            </div>
          </div>
        </div>
      </div>

      {/* Lanes per Segment */}
      <div className="grid gap-4 md:grid-cols-2">
        {topology.lanes.map((lane) => {
          const dimmed = focusedSegmentId !== null && focusedSegmentId !== lane.id;

          return <SegmentLaneCard key={lane.id} lane={lane} dimmed={dimmed} />;
        })}
      </div>
    </div>
  );
}

function SegmentLaneCard({ lane, dimmed }: { lane: TopologyLaneData; dimmed: boolean }) {
  return (
    <div
      className={cn(
        "bg-panel flex flex-col gap-3.5 rounded-xl border p-4 transition-opacity",
        dimmed && "opacity-40",
      )}
    >
      <div className="flex flex-wrap items-start justify-between gap-2 border-b pb-3">
        <div className="flex items-center gap-2.5">
          <SegmentDot color={lane.color} className="size-3" />
          <div>
            <div className="flex items-center gap-2">
              <h3 className="text-sm font-semibold">{lane.name}</h3>
              {lane.vlan_id ? (
                <Mono className="bg-card rounded border px-1.5 py-0.5 text-[11px]">
                  VLAN {lane.vlan_id}
                </Mono>
              ) : null}
            </div>
            <div className="text-muted-foreground text-xs">
              Subnet <Mono>{lane.subnet}</Mono> · Gateway <Mono>{lane.gateway}</Mono> · Port{" "}
              <Mono>{lane.interface}</Mono>
            </div>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-1.5">
          {lane.egress ? (
            <span className="bg-info-bg border-info-border text-info-fg rounded-md border px-2 py-0.5 text-[11px] font-medium">
              Keluar: {lane.egress}
            </span>
          ) : null}
          {lane.is_isolated ? (
            <span className="bg-card text-muted-foreground inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px]">
              <Shield className="size-3" aria-hidden="true" />
              Terisolasi
            </span>
          ) : null}
          {lane.bandwidth_limit ? (
            <span className="bg-card text-muted-foreground rounded-md border px-2 py-0.5 font-mono text-[11px]">
              Limit {lane.bandwidth_limit}
            </span>
          ) : null}
        </div>
      </div>

      {/* Switches in this segment */}
      <div className="flex flex-col gap-2">
        <span className="text-muted-foreground text-[11px] font-semibold uppercase">
          Switch di jalur ini ({lane.switches.length})
        </span>
        {lane.switches.length === 0 ? (
          <div className="text-muted-foreground text-xs">Terhubung langsung ke port MikroTik</div>
        ) : (
          <div className="grid gap-2 sm:grid-cols-2">
            {lane.switches.map((sw) => (
              <div
                key={sw.id}
                className="bg-card flex flex-col gap-1 rounded-lg border p-2.5 text-xs"
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="font-semibold">{sw.name}</span>
                  <Mono className="text-muted-foreground text-[11px]">{sw.total_ports} port</Mono>
                </div>
                <div className="text-muted-foreground text-[11px]">
                  {sw.location ?? "LAN"} · {sw.device_count} perangkat
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Device summary footer */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-2.5 text-xs">
        <div className="flex flex-wrap items-center gap-2">
          <span className="inline-flex items-center gap-1 font-semibold">
            <Network className="text-muted-foreground size-3.5" aria-hidden="true" />
            {lane.device_summary.total} perangkat terdaftar
          </span>
          {Object.entries(lane.device_summary.by_type).map(([typeLabel, count]) => (
            <span key={typeLabel} className="text-muted-foreground">
              · {count} {typeLabel}
            </span>
          ))}
        </div>
        <Link
          href={`/peta-ip?jalur=${lane.slug}`}
          className="text-primary font-medium hover:underline"
        >
          Lihat Peta IP →
        </Link>
      </div>
    </div>
  );
}
