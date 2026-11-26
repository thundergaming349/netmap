"use client";

import { useRef } from "react";
import { IP_STATUS_META } from "@/lib/status";
import type { IpMapCell, IpStatusCode } from "@/types/api";
import { IpCell } from "./ip-cell";

type IpGridProps = {
  cells: IpMapCell[];
  selectedOctet: number | null;
  onSelectCell: (cell: IpMapCell) => void;
};

const LEGEND_ORDER: IpStatusCode[] = [
  "gateway",
  "static",
  "dhcp",
  "reserved",
  "conflict",
  "risk",
  "offline",
  "free",
  "network",
];

export function IpGrid({ cells, selectedOctet, onSelectCell }: IpGridProps) {
  const gridRef = useRef<HTMLDivElement>(null);

  function handleKeyDown(event: React.KeyboardEvent<HTMLButtonElement>, octet: number) {
    let nextOctet: number | null = null;

    if (event.key === "ArrowRight") nextOctet = Math.min(255, octet + 1);
    else if (event.key === "ArrowLeft") nextOctet = Math.max(0, octet - 1);
    else if (event.key === "ArrowDown") nextOctet = Math.min(255, octet + 16);
    else if (event.key === "ArrowUp") nextOctet = Math.max(0, octet - 16);

    if (nextOctet !== null && nextOctet !== octet) {
      event.preventDefault();
      const targetCell = cells.find((c) => c.octet === nextOctet);
      if (targetCell) {
        onSelectCell(targetCell);
      }
      const btn = gridRef.current?.querySelector<HTMLButtonElement>(
        `button[data-octet="${nextOctet}"]`,
      );
      btn?.focus();
    }
  }

  return (
    <div className="bg-card flex flex-col gap-4 rounded-xl border p-4 md:p-5">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-base font-semibold">Peta 256 Alamat IP (.0–.255)</h2>
          <p className="text-muted-foreground text-xs">
            Klik kotak atau gunakan tombol panah keyboard untuk melihat detail per IP.
          </p>
        </div>
      </div>

      <div className="overflow-x-auto pb-1">
        <div
          ref={gridRef}
          role="grid"
          aria-label="Grid 256 alamat IP"
          className="grid min-w-[640px] grid-cols-16 gap-1.5"
        >
          {cells.map((cell) => (
            <IpCell
              key={cell.octet}
              cell={cell}
              selected={selectedOctet === cell.octet}
              onSelect={onSelectCell}
              onKeyDown={handleKeyDown}
            />
          ))}
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-t pt-3 text-xs">
        {LEGEND_ORDER.map((code) => {
          const meta = IP_STATUS_META[code];
          return (
            <div key={code} className="flex items-center gap-1.5">
              <span className={`inline-block size-3 rounded-xs ${meta.cellClass}`} />
              <span className="text-muted-foreground">{meta.label}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}
