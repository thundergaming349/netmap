"use client";

import { ExternalLink, HardDrive } from "lucide-react";
import { Mono } from "@/components/common/mono";
import { formatBytes, formatPercent } from "@/lib/format";
import type { Nvr } from "@/types/api";

export function NvrStatus({ nvrs }: { nvrs: Nvr[] }) {
  return (
    <div className="bg-card flex flex-col gap-3.5 rounded-xl border p-4">
      <div className="flex items-center gap-2">
        <HardDrive className="text-primary size-4" aria-hidden="true" />
        <h3 className="text-sm font-semibold">Status Penyimpanan NVR</h3>
      </div>

      <div className="flex flex-col gap-3">
        {nvrs.map((nvr) => (
          <div key={nvr.id} className="bg-panel flex flex-col gap-2 rounded-lg border p-3 text-xs">
            <div className="flex items-start justify-between gap-2">
              <div>
                <div className="font-semibold">{nvr.name}</div>
                <div className="text-muted-foreground text-[11px]">
                  <Mono>{nvr.ip}</Mono> · {nvr.camera_count}/{nvr.channel_count} ch
                </div>
              </div>
              <a
                href={nvr.web_url}
                target="_blank"
                rel="noreferrer"
                className="text-primary inline-flex items-center gap-1 text-[11px] hover:underline"
              >
                Web NVR <ExternalLink className="size-3" aria-hidden="true" />
              </a>
            </div>

            <div className="flex flex-col gap-1">
              <div className="flex items-center justify-between text-[11px]">
                <span className="text-muted-foreground">
                  HDD: <Mono className="text-foreground">{formatBytes(nvr.used_bytes)}</Mono> /{" "}
                  <Mono>{formatBytes(nvr.capacity_bytes)}</Mono>
                </span>
                <Mono>{formatPercent(nvr.usage_percent)}</Mono>
              </div>
              <div className="bg-background h-2 w-full overflow-hidden rounded-full">
                <div
                  className="bg-primary h-full rounded-full"
                  style={{ width: `${Math.min(100, nvr.usage_percent)}%` }}
                />
              </div>
              <div className="text-muted-foreground text-[11px]">
                Perkiraan retensi rekaman:{" "}
                <strong className="text-foreground">{nvr.retention_days} hari</strong>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
