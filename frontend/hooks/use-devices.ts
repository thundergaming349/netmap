"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, type ApiEnvelope } from "@/lib/api";
import { notify } from "@/lib/toast";
import type { Device, DeviceTrace, DeviceTypeCode } from "@/types/api";

export type DeviceFilters = {
  segment_id?: number | null;
  type?: string | null;
  status?: string | null;
  q?: string;
};

export function useDevices(filters: DeviceFilters = {}) {
  return useQuery({
    queryKey: ["devices", filters],
    queryFn: () => {
      const params = new URLSearchParams();
      if (filters.segment_id) params.set("segment_id", String(filters.segment_id));
      if (filters.type && filters.type !== "all") params.set("type", filters.type);
      if (filters.status && filters.status !== "all") params.set("status", filters.status);
      if (filters.q && filters.q.trim() !== "") params.set("q", filters.q.trim());
      params.set("per_page", "200");

      const qs = params.toString();
      return api<ApiEnvelope<Device[]>>(`/api/v1/devices${qs ? `?${qs}` : ""}`).then((r) => r.data);
    },
    refetchInterval: 10_000,
  });
}

export type SaveDeviceInput = {
  name: string;
  type: DeviceTypeCode;
  segment_id: number;
  network_switch_id?: number | null;
  ip: string;
  mac?: string | null;
  location?: string | null;
  port?: string | null;
  note?: string | null;
  monitor_ping?: boolean;
  is_static_lease?: boolean;
  is_arp_pinned?: boolean;
};

export function useCreateDevice() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: SaveDeviceInput) =>
      api<ApiEnvelope<Device>>("/api/v1/devices", { method: "POST", body: input }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["devices"] });
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      void queryClient.invalidateQueries({ queryKey: ["segments"] });
      void queryClient.invalidateQueries({ queryKey: ["dashboard-summary"] });
      void queryClient.invalidateQueries({ queryKey: ["cameras"] });
      notify.success(res.message ?? "Perangkat disimpan.");
    },
  });
}

export function useUpdateDevice() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, ...input }: Partial<SaveDeviceInput> & { id: number }) =>
      api<ApiEnvelope<Device>>(`/api/v1/devices/${id}`, { method: "PATCH", body: input }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["devices"] });
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      void queryClient.invalidateQueries({ queryKey: ["segments"] });
      void queryClient.invalidateQueries({ queryKey: ["dashboard-summary"] });
      notify.success(res.message ?? "Perangkat diperbarui.");
    },
  });
}

export function useDeleteDevice() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      api<{ message: string }>(`/api/v1/devices/${id}`, { method: "DELETE" }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["devices"] });
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      void queryClient.invalidateQueries({ queryKey: ["segments"] });
      void queryClient.invalidateQueries({ queryKey: ["dashboard-summary"] });
      notify.success(res.message);
    },
    onError: (err) => notify.apiError(err),
  });
}

export function useImportDevices() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (csv: string) =>
      api<ApiEnvelope<{ imported: number; skipped: number }>>("/api/v1/devices/import", {
        method: "POST",
        body: { csv },
      }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["devices"] });
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      void queryClient.invalidateQueries({ queryKey: ["segments"] });
      notify.success(res.message ?? "Impor CSV selesai.");
    },
    onError: (err) => notify.apiError(err),
  });
}

export function useDeviceTrace(deviceId: number | null) {
  return useQuery({
    queryKey: ["device-trace", deviceId],
    queryFn: () =>
      api<ApiEnvelope<DeviceTrace>>(`/api/v1/devices/${deviceId}/trace`).then((r) => r.data),
    enabled: deviceId !== null,
  });
}
