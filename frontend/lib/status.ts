import type {
  CameraStatusCode,
  DeviceStatusCode,
  DeviceTypeCode,
  IpStatusCode,
  SeverityCode,
} from "@/types/api";

export type StatusStyleMeta = {
  label: string;
  cellClass: string;
  badgeClass: string;
  dotClass: string;
};

export const IP_STATUS_META: Record<IpStatusCode, StatusStyleMeta> = {
  gateway: {
    label: "Gateway",
    cellClass: "bg-status-gateway text-status-gateway-fg font-semibold",
    badgeClass: "bg-status-gateway text-status-gateway-fg",
    dotClass: "bg-status-gateway",
  },
  static: {
    label: "Static",
    cellClass: "bg-status-static text-status-static-fg font-semibold",
    badgeClass: "bg-status-static text-status-static-fg",
    dotClass: "bg-status-static",
  },
  dhcp: {
    label: "DHCP lease",
    cellClass: "bg-status-dhcp text-status-dhcp-fg",
    badgeClass: "bg-status-dhcp text-status-dhcp-fg",
    dotClass: "bg-status-dhcp",
  },
  reserved: {
    label: "Reservasi",
    cellClass: "bg-status-reserved text-status-reserved-fg",
    badgeClass: "bg-status-reserved text-status-reserved-fg",
    dotClass: "bg-status-reserved",
  },
  conflict: {
    label: "Konflik",
    cellClass: "bg-status-conflict text-status-conflict-fg font-bold ring-1 ring-status-conflict",
    badgeClass: "bg-status-conflict text-status-conflict-fg font-bold",
    dotClass: "bg-status-conflict",
  },
  risk: {
    label: "Static di pool DHCP",
    cellClass: "bg-status-risk text-status-risk-fg font-semibold",
    badgeClass: "bg-status-risk text-status-risk-fg font-semibold",
    dotClass: "bg-status-risk",
  },
  offline: {
    label: "Offline",
    cellClass: "bg-status-offline text-status-offline-fg border border-warn-border",
    badgeClass: "bg-status-offline text-status-offline-fg border border-warn-border",
    dotClass: "bg-status-offline-fg",
  },
  free: {
    label: "Kosong",
    cellClass: "bg-status-free text-status-free-fg hover:bg-secondary",
    badgeClass: "bg-status-free text-status-free-fg border",
    dotClass: "bg-status-free-fg",
  },
  network: {
    label: "Network / Broadcast",
    cellClass: "bg-background text-muted-foreground/60 border border-border/70",
    badgeClass: "bg-background text-muted-foreground border",
    dotClass: "bg-muted-foreground",
  },
};

export const SEVERITY_META: Record<SeverityCode, { label: string; badgeClass: string }> = {
  conflict: {
    label: "KONFLIK",
    badgeClass: "bg-status-conflict text-status-conflict-fg font-bold",
  },
  risk: {
    label: "RAWAN",
    badgeClass: "bg-status-risk text-status-risk-fg font-bold",
  },
  info: {
    label: "INFO",
    badgeClass: "bg-status-dhcp text-status-dhcp-fg font-semibold",
  },
};

export const CAMERA_STATUS_META: Record<CameraStatusCode, { label: string; badgeClass: string }> = {
  online: {
    label: "LIVE",
    badgeClass:
      "bg-status-online-bg text-status-online-fg border border-status-online/40 font-bold",
  },
  unstable: {
    label: "TAK STABIL",
    badgeClass: "bg-status-risk text-status-risk-fg font-bold",
  },
  offline: {
    label: "OFFLINE",
    badgeClass: "bg-status-offline text-status-offline-fg border border-warn-border font-bold",
  },
};

export const DEVICE_STATUS_META: Record<DeviceStatusCode, { label: string; badgeClass: string }> = {
  online: {
    label: "Online",
    badgeClass: "bg-status-online-bg text-status-online-fg",
  },
  offline: {
    label: "Offline",
    badgeClass: "bg-status-offline text-status-offline-fg",
  },
  conflict: {
    label: "Konflik",
    badgeClass: "bg-status-conflict text-status-conflict-fg",
  },
  risk: {
    label: "Rawan",
    badgeClass: "bg-status-risk text-status-risk-fg",
  },
};

export const DEVICE_TYPE_OPTIONS: { value: DeviceTypeCode; label: string }[] = [
  { value: "camera", label: "Kamera CCTV" },
  { value: "nvr", label: "NVR" },
  { value: "switch", label: "Switch" },
  { value: "access_point", label: "Access Point" },
  { value: "server", label: "Server" },
  { value: "pc", label: "PC / Laptop" },
  { value: "printer", label: "Printer" },
  { value: "router", label: "Router" },
  { value: "other", label: "Lainnya" },
];
