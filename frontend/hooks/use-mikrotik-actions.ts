"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api, type ApiEnvelope } from "@/lib/api";
import { notify } from "@/lib/toast";

export function useSyncMikrotik() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => api<ApiEnvelope<unknown>>("/api/v1/mikrotik/sync", { method: "POST" }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["dashboard-summary"] });
      void queryClient.invalidateQueries({ queryKey: ["segments"] });
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      void queryClient.invalidateQueries({ queryKey: ["conflicts"] });
      notify.success(res.message ?? "Sinkronisasi MikroTik selesai.");
    },
    onError: (err) => notify.apiError(err),
  });
}

export function useScanArp() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (segmentId?: number | null) =>
      api<ApiEnvelope<unknown>>("/api/v1/mikrotik/scan", {
        method: "POST",
        body: segmentId ? { segment_id: segmentId } : {},
      }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["dashboard-summary"] });
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      void queryClient.invalidateQueries({ queryKey: ["conflicts"] });
      notify.success(res.message ?? "Scan ARP aktif selesai.");
    },
    onError: (err) => notify.apiError(err),
  });
}

export function useMakeLeaseStatic() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: { segment_id?: number; ip: string; mac: string; comment?: string }) =>
      api<ApiEnvelope<unknown>>("/api/v1/mikrotik/leases/make-static", {
        method: "POST",
        body: input,
      }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      void queryClient.invalidateQueries({ queryKey: ["devices"] });
      void queryClient.invalidateQueries({ queryKey: ["conflicts"] });
      notify.success(res.message ?? "DHCP lease static berhasil dibuat.");
    },
    onError: (err) => notify.apiError(err),
  });
}

export function useAddStaticArp() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: { segment_id?: number; ip: string; mac: string; comment?: string }) =>
      api<ApiEnvelope<unknown>>("/api/v1/mikrotik/arp", {
        method: "POST",
        body: input,
      }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      void queryClient.invalidateQueries({ queryKey: ["devices"] });
      notify.success(res.message ?? "ARP static berhasil dipasang.");
    },
    onError: (err) => notify.apiError(err),
  });
}

export function useBlockMac() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: { mac: string; segment_id?: number; ip?: string; reason?: string }) =>
      api<ApiEnvelope<unknown>>("/api/v1/mikrotik/mac-blocks", {
        method: "POST",
        body: input,
      }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["settings", "mikrotik"] });
      void queryClient.invalidateQueries({ queryKey: ["audit-logs"] });
      notify.success(res.message ?? "MAC berhasil diblokir.");
    },
    onError: (err) => notify.apiError(err),
  });
}

export function useUnblockMac() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      api<{ message: string }>(`/api/v1/mikrotik/mac-blocks/${id}`, { method: "DELETE" }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["settings", "mikrotik"] });
      void queryClient.invalidateQueries({ queryKey: ["audit-logs"] });
      notify.success(res.message);
    },
    onError: (err) => notify.apiError(err),
  });
}

export function useRestartPoe() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (interfaceName: string) =>
      api<ApiEnvelope<unknown>>(
        `/api/v1/mikrotik/poe/${encodeURIComponent(interfaceName)}/restart`,
        { method: "POST" },
      ),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["cameras"] });
      void queryClient.invalidateQueries({ queryKey: ["devices"] });
      void queryClient.invalidateQueries({ queryKey: ["conflicts"] });
      notify.success(res.message ?? "Port PoE berhasil di-restart.");
    },
    onError: (err) => notify.apiError(err),
  });
}
