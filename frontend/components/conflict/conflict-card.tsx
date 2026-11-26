"use client";

import { Mono } from "@/components/common/mono";
import { SegmentDot } from "@/components/segment/segment-dot";
import { SeverityBadge } from "@/components/status/status-badge";
import { formatMac, formatRelativeTime } from "@/lib/format";
import { cn } from "@/lib/utils";
import type { ConflictEvent } from "@/types/api";

type ConflictCardProps = {
  event: ConflictEvent;
  selected?: boolean;
  onSelect: (event: ConflictEvent) => void;
};

export function ConflictCard({ event, selected = false, onSelect }: ConflictCardProps) {
  return (
    <button
      type="button"
      onClick={() => onSelect(event)}
      className={cn(
        "bg-card flex w-full flex-col gap-2 rounded-xl border p-4 text-left transition-colors",
        selected ? "border-primary bg-secondary/40" : "hover:bg-panel/80",
      )}
    >
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <SeverityBadge severity={event.severity} />
          <Mono className="text-sm font-bold">{event.ip}</Mono>
          {event.segment ? (
            <span className="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
              <SegmentDot color={event.segment.color} />
              {event.segment.name}
            </span>
          ) : null}
        </div>
        <span className="text-muted-foreground text-xs">
          {event.status === "resolved"
            ? `Selesai · ${formatRelativeTime(event.resolved_at)}`
            : formatRelativeTime(event.last_detected_at)}
        </span>
      </div>

      <div className="text-sm font-semibold">{event.title}</div>
      <p className="text-muted-foreground line-clamp-2 text-xs leading-relaxed">{event.summary}</p>

      {event.macs.length > 0 ? (
        <div className="flex flex-wrap items-center gap-1.5 pt-0.5">
          {event.macs.map((mac) => (
            <Mono
              key={mac}
              className="bg-panel text-muted-foreground rounded border px-1.5 py-0.5 text-[11px]"
            >
              {formatMac(mac)}
            </Mono>
          ))}
        </div>
      ) : null}
    </button>
  );
}
