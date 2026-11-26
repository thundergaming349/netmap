"use client";

import { IP_STATUS_META } from "@/lib/status";
import { cn } from "@/lib/utils";
import type { IpMapCell } from "@/types/api";

type IpCellProps = {
  cell: IpMapCell;
  selected: boolean;
  onSelect: (cell: IpMapCell) => void;
  onKeyDown?: (event: React.KeyboardEvent<HTMLButtonElement>, octet: number) => void;
};

export function IpCell({ cell, selected, onSelect, onKeyDown }: IpCellProps) {
  const meta = IP_STATUS_META[cell.status] ?? IP_STATUS_META.free;
  const label = cell.device?.name
    ? `${cell.ip} — ${cell.status_label} (${cell.device.name})`
    : `${cell.ip} — ${cell.status_label}`;

  return (
    <button
      type="button"
      data-octet={cell.octet}
      aria-label={label}
      aria-pressed={selected}
      title={label}
      onClick={() => onSelect(cell)}
      onKeyDown={(e) => onKeyDown?.(e, cell.octet)}
      className={cn(
        "flex h-9 w-full items-center justify-center rounded-md font-mono text-xs transition-transform focus-visible:ring-2 focus-visible:outline-none",
        meta.cellClass,
        selected && "ring-primary z-10 scale-105 ring-2",
      )}
    >
      .{cell.octet}
    </button>
  );
}
