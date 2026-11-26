"use client";

import { Maximize2 } from "lucide-react";
import { Mono } from "@/components/common/mono";
import { CameraStatusBadge } from "@/components/status/status-badge";
import type { Camera } from "@/types/api";
import { CameraPlayer } from "./camera-player";

type CameraTileProps = {
  camera: Camera;
  onExpand: (camera: Camera) => void;
};

export function CameraTile({ camera, onExpand }: CameraTileProps) {
  return (
    <div className="bg-card group hover:border-primary/50 flex flex-col overflow-hidden rounded-xl border transition-colors">
      <button
        type="button"
        onClick={() => onExpand(camera)}
        aria-label={`Perbesar kamera ${camera.name} (${camera.ip})`}
        className="relative block w-full text-left focus-visible:ring-2 focus-visible:outline-none"
      >
        <CameraPlayer camera={camera} quality="sub" />
      </button>

      <div className="flex items-center justify-between gap-2 border-t px-3.5 py-2.5 text-xs">
        <div className="min-w-0">
          <div className="truncate font-semibold">{camera.name}</div>
          <div className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
            <Mono>{camera.ip}</Mono>
            <span>·</span>
            <span className="truncate">{camera.location_group}</span>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <CameraStatusBadge status={camera.status} />
          <button
            type="button"
            onClick={() => onExpand(camera)}
            aria-label={`Layar besar ${camera.name}`}
            className="text-muted-foreground hover:text-foreground rounded p-1"
          >
            <Maximize2 className="size-3.5" aria-hidden="true" />
          </button>
        </div>
      </div>
    </div>
  );
}
