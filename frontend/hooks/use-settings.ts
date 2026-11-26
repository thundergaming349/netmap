"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, type ApiEnvelope } from "@/lib/api";
import { notify } from "@/lib/toast";
import type { AuditLogEntry, BackupEntry } from "@/types/api";

export type SettingsGroupName = "mikrotik" | "sync" | "rules" | "notifications" | "cctv" | "backup";

export function useSettingsGroup<T = Record<string, unknown>>(group: SettingsGroupName) {
  return useQuery({
    queryKey: ["settings", group],
    queryFn: () => api<ApiEnvelope<T>>(`/api/v1/settings/${group}`).then((r) => r.data),
  });
}

export function useUpdateSettingsGroup(group: SettingsGroupName) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: Record<string, unknown>) =>
      api<ApiEnvelope<Record<string, unknown>>>(`/api/v1/settings/${group}`, {
        method: "PUT",
        body: values,
      }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["settings", group] });
      void queryClient.invalidateQueries({ queryKey: ["dashboard-summary"] });
      notify.success(res.message ?? "Pengaturan disimpan.");
    },
    onError: (err) => notify.apiError(err),
  });
}

export function useTestMikrotikConnection() {
  return useMutation({
    mutationFn: () =>
      api<
        ApiEnvelope<{
          connected: boolean;
          version: string;
          board_name: string;
          uptime: string;
          cpu_load: number;
        }>
      >("/api/v1/settings/mikrotik/test", { method: "POST" }),
    onSuccess: (res) => notify.success(res.message ?? "Koneksi MikroTik berhasil."),
    onError: (err) => notify.apiError(err),
  });
}

export function useTestTelegramNotification() {
  return useMutation({
    mutationFn: () =>
      api<ApiEnvelope<{ sent: boolean; message: string }>>("/api/v1/settings/notifications/test", {
        method: "POST",
      }),
    onSuccess: (res) => notify.success(res.message ?? "Notifikasi tes terkirim."),
    onError: (err) => notify.apiError(err),
  });
}

export function useAuditLogs(q: string = "") {
  return useQuery({
    queryKey: ["audit-logs", q],
    queryFn: () => {
      const qs = q.trim() !== "" ? `?q=${encodeURIComponent(q.trim())}` : "";
      return api<ApiEnvelope<AuditLogEntry[]>>(`/api/v1/audit-logs${qs}`).then((r) => r.data);
    },
  });
}

export function useBackups() {
  return useQuery({
    queryKey: ["backups"],
    queryFn: () => api<ApiEnvelope<BackupEntry[]>>("/api/v1/backups").then((r) => r.data),
  });
}

export function useCreateBackup() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => api<ApiEnvelope<BackupEntry>>("/api/v1/backups", { method: "POST" }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["backups"] });
      void queryClient.invalidateQueries({ queryKey: ["audit-logs"] });
      notify.success(res.message ?? "Backup berhasil dibuat.");
    },
    onError: (err) => notify.apiError(err),
  });
}
