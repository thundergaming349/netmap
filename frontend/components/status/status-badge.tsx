import {
  CAMERA_STATUS_META,
  DEVICE_STATUS_META,
  IP_STATUS_META,
  SEVERITY_META,
} from "@/lib/status";
import { cn } from "@/lib/utils";
import type { CameraStatusCode, DeviceStatusCode, IpStatusCode, SeverityCode } from "@/types/api";

export function StatusBadge({
  status,
  label,
  className,
}: {
  status: IpStatusCode;
  label?: string;
  className?: string;
}) {
  const meta = IP_STATUS_META[status] ?? IP_STATUS_META.free;

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap",
        meta.badgeClass,
        className,
      )}
    >
      {label ?? meta.label}
    </span>
  );
}

export function SeverityBadge({
  severity,
  className,
}: {
  severity: SeverityCode;
  className?: string;
}) {
  const meta = SEVERITY_META[severity] ?? SEVERITY_META.info;

  return (
    <span
      className={cn(
        "inline-flex items-center rounded-md px-2 py-0.5 text-[11px] tracking-wider whitespace-nowrap uppercase",
        meta.badgeClass,
        className,
      )}
    >
      {meta.label}
    </span>
  );
}

export function CameraStatusBadge({
  status,
  className,
}: {
  status: CameraStatusCode;
  className?: string;
}) {
  const meta = CAMERA_STATUS_META[status] ?? CAMERA_STATUS_META.offline;

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11px] tracking-wide whitespace-nowrap",
        meta.badgeClass,
        className,
      )}
    >
      {status === "online" ? (
        <span className="bg-status-online size-1.5 rounded-full" aria-hidden="true" />
      ) : null}
      {meta.label}
    </span>
  );
}

export function DeviceStatusBadge({
  status,
  className,
}: {
  status: DeviceStatusCode;
  className?: string;
}) {
  const meta = DEVICE_STATUS_META[status] ?? DEVICE_STATUS_META.online;

  return (
    <span
      className={cn(
        "inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap",
        meta.badgeClass,
        className,
      )}
    >
      {meta.label}
    </span>
  );
}
