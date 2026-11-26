"use client";

import { Radar } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { EmptyState } from "@/components/common/empty-state";
import { ErrorState } from "@/components/common/error-state";
import { PageHeader } from "@/components/common/page-header";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useConflicts } from "@/hooks/use-conflicts";
import { useScanArp } from "@/hooks/use-mikrotik-actions";
import { cn } from "@/lib/utils";
import { ConflictCard } from "./conflict-card";
import { ConflictDetail } from "./conflict-detail";

const STATUS_TABS: { value: "active" | "resolved" | "all"; label: string }[] = [
  { value: "active", label: "Aktif" },
  { value: "resolved", label: "Selesai" },
  { value: "all", label: "Semua Riwayat" },
];

export function ConflictsView() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const scanArp = useScanArp();

  const statusParam = searchParams.get("status");
  const initialTab: "active" | "resolved" | "all" =
    statusParam === "resolved" || statusParam === "all" ? statusParam : "active";
  const idParam = searchParams.get("id");

  const [tab, setTab] = useState<"active" | "resolved" | "all">(initialTab);
  const [selectedId, setSelectedId] = useState<number | null>(
    idParam ? Number.parseInt(idParam, 10) : null,
  );

  const conflictsQuery = useConflicts(tab);

  function handleTabChange(nextTab: "active" | "resolved" | "all") {
    setTab(nextTab);
    const params = new URLSearchParams(searchParams.toString());
    params.set("status", nextTab);
    params.delete("id");
    router.replace(`/konflik?${params.toString()}`, { scroll: false });
  }

  if (conflictsQuery.isPending) {
    return (
      <div className="flex flex-col gap-5">
        <Skeleton className="h-12 w-72" />
        <Skeleton className="h-96 w-full" />
      </div>
    );
  }

  if (conflictsQuery.isError) {
    return (
      <ErrorState
        message={conflictsQuery.error?.message}
        onRetry={() => void conflictsQuery.refetch()}
      />
    );
  }

  const events = conflictsQuery.data ?? [];
  const selectedEvent = events.find((e) => e.id === selectedId) ?? events[0] ?? null;

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <PageHeader
          title="Log Konflik & Peringatan IP"
          description="Deteksi bentrok IP duplikat, penyalahgunaan reservasi, IP static di dalam pool DHCP, dan kamera offline beserta kronologi dan saran perbaikan."
        />
        <Button variant="outline" onClick={() => scanArp.mutate(null)} disabled={scanArp.isPending}>
          <Radar className="size-4" aria-hidden="true" />
          {scanArp.isPending ? "Memindai ARP…" : "Scan ARP Sekarang"}
        </Button>
      </div>

      {/* Status Tabs */}
      <div role="tablist" aria-label="Status konflik" className="flex flex-wrap gap-2">
        {STATUS_TABS.map((item) => {
          const active = tab === item.value;
          return (
            <button
              key={item.value}
              type="button"
              role="tab"
              aria-selected={active}
              onClick={() => handleTabChange(item.value)}
              className={cn(
                "flex min-h-10 items-center gap-2 rounded-lg border px-4 py-2 text-sm transition-colors",
                active
                  ? "bg-secondary text-foreground border-primary/60 font-semibold"
                  : "bg-card text-muted-foreground hover:text-foreground",
              )}
            >
              {item.label}
            </button>
          );
        })}
      </div>

      {events.length === 0 ? (
        <EmptyState
          title="Tidak ada kejadian pada tab ini"
          description="Semua alamat IP berjalan normal tanpa bentrok yang terdeteksi."
        />
      ) : (
        <div className="flex flex-wrap items-start gap-5">
          <div className="flex flex-col gap-3" style={{ flex: "999 1 480px" }}>
            {events.map((event) => (
              <ConflictCard
                key={event.id}
                event={event}
                selected={selectedEvent?.id === event.id}
                onSelect={(ev) => setSelectedId(ev.id)}
              />
            ))}
          </div>

          <div style={{ flex: "1 1 360px" }}>
            <ConflictDetail event={selectedEvent} />
          </div>
        </div>
      )}
    </div>
  );
}
