"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { ErrorState } from "@/components/common/error-state";
import { Mono } from "@/components/common/mono";
import { PageHeader } from "@/components/common/page-header";
import { SegmentDot } from "@/components/segment/segment-dot";
import { SegmentTabs } from "@/components/segment/segment-tabs";
import { Skeleton } from "@/components/ui/skeleton";
import { useSegments } from "@/hooks/use-segments";
import { useRouterRoutes, useTopology } from "@/hooks/use-topology";
import { cn } from "@/lib/utils";
import { TopologyLane } from "./topology-lane";
import { TraceTimeline } from "./trace-timeline";

export function RouteView() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const segmentsQuery = useSegments();
  const topologyQuery = useTopology();
  const routesQuery = useRouterRoutes();

  const slugParam = searchParams.get("jalur");
  const deviceParam = searchParams.get("device");

  const segments = segmentsQuery.data ?? [];
  const focusedSegment = segments.find((s) => s.slug === slugParam) ?? null;
  const selectedDeviceId = deviceParam ? Number.parseInt(deviceParam, 10) : null;

  function updateQuery(nextSlug: string | null, nextDeviceId: number | null) {
    const params = new URLSearchParams(searchParams.toString());
    if (nextSlug) params.set("jalur", nextSlug);
    else params.delete("jalur");

    if (nextDeviceId) params.set("device", String(nextDeviceId));
    else params.delete("device");

    const qs = params.toString();
    router.replace(`/route${qs ? `?${qs}` : ""}`, { scroll: false });
  }

  if (topologyQuery.isPending) {
    return (
      <div className="flex flex-col gap-5">
        <Skeleton className="h-12 w-72" />
        <Skeleton className="h-80 w-full" />
        <Skeleton className="h-48 w-full" />
      </div>
    );
  }

  if (topologyQuery.isError || !topologyQuery.data) {
    return (
      <ErrorState
        message={topologyQuery.error?.message}
        onRetry={() => void topologyQuery.refetch()}
      />
    );
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="Route per Jalur"
        description="Topologi dari Internet → ISP → MikroTik → Switch → Perangkat, trace hop per perangkat, dan tabel /ip route."
      />

      <SegmentTabs
        segments={segments}
        selectedId={focusedSegment?.id ?? null}
        includeAll
        onSelectAll={() => updateQuery(null, selectedDeviceId)}
        onSelect={(seg) => updateQuery(seg.slug, selectedDeviceId)}
      />

      <TopologyLane topology={topologyQuery.data} focusedSegmentId={focusedSegment?.id ?? null} />

      <TraceTimeline
        selectedDeviceId={selectedDeviceId}
        onSelectDevice={(id) => updateQuery(focusedSegment?.slug ?? null, id)}
      />

      {/* Read-only /ip route table */}
      <section className="bg-card overflow-hidden rounded-xl border">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4">
          <div>
            <h2 className="text-base font-semibold">
              Tabel Routing MikroTik (<Mono>/ip route</Mono>)
            </h2>
            <p className="text-muted-foreground text-xs">
              Hanya-baca. Perubahan policy routing dan failover tetap dilakukan langsung di Winbox.
            </p>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[720px] border-collapse text-[13px]">
            <thead>
              <tr className="text-muted-foreground border-b text-left text-xs">
                <th className="px-5 py-3 font-medium">Status</th>
                <th className="px-3 py-3 font-medium">Dst. Address</th>
                <th className="px-3 py-3 font-medium">Gateway</th>
                <th className="px-3 py-3 font-medium">Routing Table</th>
                <th className="px-3 py-3 font-medium">Distance</th>
                <th className="px-3 py-3 font-medium">Jalur yang Memakai</th>
                <th className="px-5 py-3 font-medium">Keterangan</th>
              </tr>
            </thead>
            <tbody>
              {(routesQuery.data ?? []).map((route) => (
                <tr key={route.id} className="hover:bg-panel/60 border-t transition-colors">
                  <td className="px-5 py-3">
                    <span
                      className={cn(
                        "rounded px-1.5 py-0.5 font-mono text-[11px] font-bold",
                        route.is_active
                          ? "bg-status-online-bg text-status-online-fg"
                          : "bg-status-free text-status-free-fg",
                      )}
                    >
                      {route.is_dynamic ? "DAC" : route.is_active ? "AS" : "S (Standby)"}
                    </span>
                  </td>
                  <td className="px-3 py-3">
                    <Mono className="font-semibold">{route.dst_address}</Mono>
                  </td>
                  <td className="px-3 py-3">
                    <Mono>{route.gateway}</Mono>
                  </td>
                  <td className="px-3 py-3">
                    <Mono className="bg-panel rounded border px-1.5 py-0.5 text-xs">
                      {route.routing_table}
                    </Mono>
                  </td>
                  <td className="px-3 py-3">
                    <Mono>{route.distance}</Mono>
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex flex-wrap gap-1.5">
                      {route.segments.map((seg) => (
                        <span
                          key={seg.id}
                          className="bg-panel inline-flex items-center gap-1.5 rounded border px-2 py-0.5 text-xs"
                        >
                          <SegmentDot color={seg.color} />
                          {seg.name}
                        </span>
                      ))}
                    </div>
                  </td>
                  <td className="text-muted-foreground px-5 py-3 text-xs">
                    {route.comment ?? "—"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}
