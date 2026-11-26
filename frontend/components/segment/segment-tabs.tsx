"use client";

import { Mono } from "@/components/common/mono";
import { cn } from "@/lib/utils";
import type { Segment } from "@/types/api";
import { SegmentDot } from "./segment-dot";

type SegmentTabsProps = {
  segments: Segment[];
  selectedId: number | null;
  onSelect: (segment: Segment) => void;
  includeAll?: boolean;
  onSelectAll?: () => void;
};

export function SegmentTabs({
  segments,
  selectedId,
  onSelect,
  includeAll = false,
  onSelectAll,
}: SegmentTabsProps) {
  return (
    <div
      role="tablist"
      aria-label="Pilih jalur jaringan"
      className="flex flex-wrap items-center gap-2"
    >
      {includeAll ? (
        <button
          type="button"
          role="tab"
          aria-selected={selectedId === null}
          onClick={onSelectAll}
          className={cn(
            "flex min-h-10 items-center gap-2 rounded-lg border px-3.5 py-2 text-sm transition-colors",
            selectedId === null
              ? "bg-secondary text-foreground border-primary/50 font-semibold"
              : "bg-card text-muted-foreground hover:text-foreground",
          )}
        >
          Semua Jalur
        </button>
      ) : null}

      {segments.map((seg) => {
        const active = seg.id === selectedId;

        return (
          <button
            key={seg.id}
            type="button"
            role="tab"
            aria-selected={active}
            onClick={() => onSelect(seg)}
            className={cn(
              "flex min-h-10 items-center gap-2.5 rounded-lg border px-3.5 py-2 text-sm transition-colors",
              active
                ? "bg-secondary text-foreground border-primary/60 font-semibold"
                : "bg-card text-muted-foreground hover:text-foreground",
            )}
          >
            <SegmentDot color={seg.color} />
            <span>{seg.name}</span>
            {seg.vlan_id ? (
              <Mono className="text-muted-foreground text-xs">VLAN {seg.vlan_id}</Mono>
            ) : null}
            {seg.active_conflict_count > 0 ? (
              <span className="bg-status-conflict text-status-conflict-fg py-0.2 rounded px-1.5 text-[10px] font-bold">
                {seg.active_conflict_count}
              </span>
            ) : null}
          </button>
        );
      })}
    </div>
  );
}
