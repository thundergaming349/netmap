"use client";

import { cn } from "@/lib/utils";
import type { Camera } from "@/types/api";
import { CameraTile } from "./camera-tile";

type CameraGridProps = {
  cameras: Camera[];
  layout: 1 | 4 | 9 | 16;
  onExpand: (camera: Camera) => void;
};

export function CameraGrid({ cameras, layout, onExpand }: CameraGridProps) {
  const gridColsClass =
    layout === 1
      ? "grid-cols-1"
      : layout === 4
        ? "grid-cols-1 sm:grid-cols-2"
        : layout === 9
          ? "grid-cols-1 sm:grid-cols-2 xl:grid-cols-3"
          : "grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4";

  return (
    <div className={cn("grid gap-3.5", gridColsClass)}>
      {cameras.map((camera) => (
        <CameraTile key={camera.id} camera={camera} onExpand={onExpand} />
      ))}
    </div>
  );
}
