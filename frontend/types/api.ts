// Mirrors the Laravel API Resources in backend/app/Http/Resources/V1.

export type RoleCode = "admin" | "technician" | "cctv_viewer";

export type PermissionCode =
  | "view-network"
  | "manage-devices"
  | "manage-reservations"
  | "resolve-conflicts"
  | "view-cctv"
  | "mikrotik-write"
  | "manage-settings"
  | "manage-users";

export type UserStatusCode = "invited" | "active" | "disabled";

export type IpStatusCode =
  "gateway" | "static" | "dhcp" | "reserved" | "conflict" | "risk" | "offline" | "free" | "network";

export type DeviceTypeCode =
  "camera" | "nvr" | "switch" | "access_point" | "server" | "pc" | "printer" | "router" | "other";

export type DeviceStatusCode = "online" | "offline" | "conflict" | "risk";

export type ConflictTypeCode =
  | "dup_ip"
  | "res_misuse"
  | "reg_mismatch"
  | "static_in_pool"
  | "out_of_subnet"
  | "mac_flap"
  | "new_device"
  | "cam_offline";

export type SeverityCode = "conflict" | "risk" | "info";

export type ConflictStatusCode = "active" | "resolved";

export type CameraStatusCode = "online" | "unstable" | "offline";

export type Me = {
  id: number;
  email: string;
  name: string | null;
  avatar_url: string | null;
  role: RoleCode | null;
  role_label: string | null;
  roles: RoleCode[];
  permissions: PermissionCode[];
  home_path: string;
};

export type User = {
  id: number;
  email: string;
  name: string | null;
  avatar_url: string | null;
  status: UserStatusCode;
  status_label: string;
  role: RoleCode | null;
  role_label: string | null;
  role_description: string | null;
  has_signed_in: boolean;
  last_login_at: string | null;
  is_self: boolean;
  created_at: string | null;
};

export type Segment = {
  id: number;
  router_id: number | null;
  name: string;
  slug: string;
  vlan_id: number | null;
  subnet: string;
  gateway: string;
  interface: string;
  pool_start: string | null;
  pool_end: string | null;
  static_start: number;
  static_end: number;
  egress: string | null;
  color: string;
  is_isolated: boolean;
  bandwidth_limit: string | null;
  notes: string | null;
  used_ips: number;
  usable_ips: number;
  usage_percent: number;
  device_count: number;
  dhcp_lease_count: number;
  active_conflict_count: number;
  arp_active_count: number;
  next_free_ip: string | null;
};

export type IpMapCell = {
  octet: number;
  ip: string;
  status: IpStatusCode;
  status_label: string;
  in_pool: boolean;
  device: {
    id: number;
    name: string;
    type: DeviceTypeCode;
    type_label: string;
    status: DeviceStatusCode;
    location: string | null;
    port: string | null;
    is_static_lease: boolean;
    is_arp_pinned: boolean;
    latency_ms: number | null;
  } | null;
  lease: {
    host_name: string | null;
    is_dynamic: boolean;
    server: string | null;
  } | null;
  reservation: {
    id: number;
    label: string;
    range: string;
    description: string | null;
  } | null;
  conflict: {
    id: number;
    type: ConflictTypeCode;
    severity: SeverityCode;
    title: string;
    summary: string;
    suggestion: string | null;
    macs: string[];
  } | null;
  mac: string | null;
  last_seen_at: string | null;
  note: string | null;
};

export type IpMapData = {
  segment: {
    id: number;
    name: string;
    slug: string;
    vlan_id: number | null;
    subnet: string;
    gateway: string;
    interface: string;
    pool_start: string | null;
    pool_end: string | null;
    static_start: number;
    static_end: number;
    egress: string | null;
    color: string;
    is_isolated: boolean;
    bandwidth_limit: string | null;
  };
  summary: {
    used_count: number;
    usable_total: number;
    free_count: number;
    reserved_count: number;
    conflict_count: number;
    risk_count: number;
    offline_count: number;
    static_count: number;
    dhcp_count: number;
    next_free_ip: string | null;
  };
  cells: IpMapCell[];
};

export type IpCheckResult = {
  ip: string;
  result: "free" | "used" | "reserved" | "in_pool" | "gateway" | "network" | "out_of_segment";
  safe: boolean;
  title: string;
  message: string;
  segment_id: number | null;
  segment_name: string | null;
  suggestion: string | null;
  holder: {
    id?: number;
    name: string;
    mac: string | null;
    type: string;
    location?: string | null;
  } | null;
};

export type IpReservation = {
  id: number;
  segment_id: number;
  segment_name: string | null;
  segment_color: string | null;
  ip_start: string;
  ip_end: string;
  start_octet: number;
  end_octet: number;
  range_label: string;
  count: number;
  label: string;
  description: string | null;
  created_at: string | null;
};

export type Device = {
  id: number;
  name: string;
  type: DeviceTypeCode;
  type_label: string;
  status: DeviceStatusCode;
  status_label: string;
  ip_status: IpStatusCode;
  ip_status_label: string;
  segment_id: number;
  segment: {
    id: number;
    name: string;
    slug: string;
    vlan_id: number | null;
    color: string;
    subnet: string;
  } | null;
  network_switch_id: number | null;
  switch_name: string | null;
  ip: string;
  mac: string | null;
  location: string | null;
  port: string | null;
  note: string | null;
  monitor_ping: boolean;
  is_static_lease: boolean;
  is_arp_pinned: boolean;
  latency_ms: number | null;
  last_seen_at: string | null;
  created_at: string | null;
};

export type TraceHop = {
  step: number;
  kind: "device" | "switch" | "router" | "nvr" | "uplink";
  name: string;
  subtitle: string;
  ip: string | null;
  mac: string | null;
  port: string | null;
  status: string;
  latency_ms: number | null;
};

export type DeviceTrace = {
  device: {
    id: number;
    name: string;
    type: DeviceTypeCode;
    type_label: string;
    ip: string;
    mac: string | null;
    status: DeviceStatusCode;
    location: string | null;
    port: string | null;
  };
  segment: {
    id: number | null;
    name: string | null;
    vlan_id: number | null;
    subnet: string | null;
    gateway: string | null;
    interface: string | null;
    egress: string | null;
    color: string | null;
  };
  hops: TraceHop[];
};

export type Uplink = {
  id: number;
  name: string;
  provider: string | null;
  interface: string;
  gateway: string;
  check_target: string;
  distance: number;
  status: "up" | "standby" | "down";
  latency_ms: number | null;
  rx_mbps: number;
  tx_mbps: number;
  last_changed_at?: string | null;
};

export type TopologySwitch = {
  id: number;
  name: string;
  ip: string | null;
  mac: string | null;
  uplink_port: string | null;
  total_ports: number;
  is_managed: boolean;
  location: string | null;
  device_count: number;
  online_count: number;
  offline_count: number;
};

export type TopologyLaneData = {
  id: number;
  name: string;
  slug: string;
  vlan_id: number | null;
  subnet: string;
  gateway: string;
  interface: string;
  egress: string | null;
  color: string;
  is_isolated: boolean;
  bandwidth_limit: string | null;
  notes: string | null;
  switches: TopologySwitch[];
  device_summary: {
    total: number;
    online: number;
    offline: number;
    conflict: number;
    by_type: Record<string, number>;
  };
};

export type TopologyData = {
  router: {
    id: number;
    name: string;
    host: string;
    version: string | null;
    uptime: string | null;
    cpu_load: number | null;
  } | null;
  uplinks: Uplink[];
  lanes: TopologyLaneData[];
  switches: {
    id: number;
    segment_id: number | null;
    name: string;
    ip: string | null;
    location: string | null;
    total_ports: number;
    is_managed: boolean;
  }[];
};

export type RouterRoute = {
  id: number;
  dst_address: string;
  gateway: string;
  routing_table: string;
  distance: number;
  scope: number | null;
  is_active: boolean;
  is_dynamic: boolean;
  comment: string | null;
  segments: {
    id: number;
    name: string;
    color: string;
    vlan_id: number | null;
  }[];
};

export type ConflictLogItem = {
  id: number;
  logged_at: string;
  message: string;
  actor_name: string;
};

export type ConflictEvent = {
  id: number;
  type: ConflictTypeCode;
  type_label: string;
  severity: SeverityCode;
  severity_label: string;
  status: ConflictStatusCode;
  status_label: string;
  segment_id: number;
  segment: {
    id: number;
    name: string;
    slug: string;
    vlan_id: number | null;
    color: string;
    interface: string;
  } | null;
  ip: string;
  macs: string[];
  title: string;
  summary: string;
  suggestion: string;
  suggested_free_ip: string | null;
  source: string;
  device: {
    id: number;
    name: string;
    type: DeviceTypeCode;
    type_label: string;
    mac: string | null;
    location: string | null;
    port: string | null;
  } | null;
  opened_at: string;
  last_detected_at: string;
  resolved_at: string | null;
  resolved_by_name: string | null;
  resolution_note: string | null;
  logs?: ConflictLogItem[];
};

export type Camera = {
  id: number;
  device_id: number;
  nvr_id: number | null;
  nvr_name: string | null;
  nvr_web_url: string | null;
  channel: number;
  name: string;
  ip: string;
  mac: string | null;
  segment_id: number | null;
  port: string | null;
  switch_name: string | null;
  brand: string;
  location_group: string;
  stream_key: string;
  status: CameraStatusCode;
  status_label: string;
  fps: number;
  resolution: string;
  latency_ms: number | null;
  last_ok_at: string | null;
  last_snapshot_at: string | null;
  stream_ready?: boolean;
  webrtc_sub_url: string;
  webrtc_main_url: string;
};

export type CameraRecording = {
  id: number;
  camera_id: number;
  date: string;
  slot_label: string;
  started_at: string;
  ended_at: string;
  duration_seconds: number;
  duration_label: string;
  filename: string;
  size_bytes: number;
  status: "recording" | "ready";
  download_url: string;
};

export type CamerasResponse = {
  data: Camera[];
  meta: {
    total: number;
    online: number;
    unstable: number;
    offline: number;
    groups: { name: string; count: number }[];
  };
};

export type Nvr = {
  id: number;
  device_id: number | null;
  name: string;
  ip: string;
  brand: string;
  web_url: string;
  channel_count: number;
  camera_count: number;
  capacity_bytes: number;
  used_bytes: number;
  usage_percent: number;
  retention_days: number;
  status: string;
  last_ok_at: string | null;
};

export type DashboardSummary = {
  router: {
    id: number;
    name: string;
    host: string;
    version: string | null;
    uptime: string | null;
    cpu_load: number | null;
    connected: boolean;
    last_synced_at: string | null;
    last_error: string | null;
  } | null;
  stats: {
    used_ips: number;
    total_usable_ips: number;
    static_devices: number;
    dhcp_leases: number;
    active_segments: number;
    cameras_online: number;
    cameras_unstable: number;
    cameras_offline: number;
    cameras_total: number;
    active_conflicts: number;
    active_risks: number;
    total_active_events: number;
  };
  warnings: ConflictEvent[];
  segments: Segment[];
  uplinks: Uplink[];
  activities: {
    id: number;
    action: string;
    target: string | null;
    actor: string;
    created_at: string | null;
  }[];
  write_enabled: boolean;
};

export type AuditLogEntry = {
  id: number;
  action: string;
  target: string | null;
  ip: string | null;
  user: {
    id: number;
    name: string;
    email: string;
  } | null;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  meta: Record<string, unknown> | null;
  created_at: string | null;
};

export type BackupEntry = {
  id: number;
  filename: string;
  size_bytes: number;
  includes_mikrotik_export: boolean;
  status: string;
  created_by_name: string;
  created_at: string;
};

export type MacBlockEntry = {
  id: number;
  mac: string;
  ip: string | null;
  reason: string | null;
  routeros_id: string | null;
  created_at: string | null;
};
