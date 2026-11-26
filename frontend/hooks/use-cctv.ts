"use client";

import { type QueryClient, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, type ApiEnvelope } from "@/lib/api";
import { notify } from "@/lib/toast";
import type { CameraRecording, CamerasResponse, Nvr } from "@/types/api";

/**
 * Cancel in-flight CCTV requests and purge all CCTV data from client-side memory.
 */
export function clearCctvClientCache(queryClient: QueryClient): void {
  void queryClient.cancelQueries({ queryKey: ["cameras"] });
  void queryClient.cancelQueries({ queryKey: ["nvrs"] });
  void queryClient.cancelQueries({ queryKey: ["camera-recordings"] });
  queryClient.removeQueries({ queryKey: ["cameras"] });
  queryClient.removeQueries({ queryKey: ["nvrs"] });
  queryClient.removeQueries({ queryKey: ["camera-recordings"] });
}

export function useCameraRecordings(cameraId: number | null, date?: string) {
  return useQuery({
    queryKey: ["camera-recordings", cameraId, date ?? "today"],
    queryFn: () => {
      const qs = date ? `?date=${encodeURIComponent(date)}` : "";
      return api<ApiEnvelope<CameraRecording[]>>(
        `/api/v1/cameras/${cameraId}/recordings${qs}`,
      ).then((r) => r.data);
    },
    enabled: cameraId !== null,
    staleTime: 0,
    gcTime: 0,
    refetchOnMount: "always",
  });
}

export function useCameras(group: string = "all") {
  return useQuery({
    queryKey: ["cameras", group],
    queryFn: () => {
      const qs = group && group !== "all" ? `?group=${encodeURIComponent(group)}` : "";
      return api<CamerasResponse>(`/api/v1/cameras${qs}`);
    },
    staleTime: 0,
    gcTime: 0,
    refetchOnMount: "always",
    refetchInterval: 30_000,
  });
}

export function useNvrs() {
  return useQuery({
    queryKey: ["nvrs"],
    queryFn: () => api<ApiEnvelope<Nvr[]>>("/api/v1/nvrs").then((r) => r.data),
    staleTime: 0,
    gcTime: 0,
    refetchOnMount: "always",
  });
}

export function useCaptureSnapshot() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (cameraId: number) =>
      api<ApiEnvelope<{ captured_at: string; snapshot_url: string }>>(
        `/api/v1/cameras/${cameraId}/snapshot`,
        { method: "POST" },
      ),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["cameras"] });
      notify.success(res.message ?? "Snapshot berhasil diambil.");
    },
    onError: (err) => notify.apiError(err),
  });
}

export function usePingCamera() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (cameraId: number) =>
      api<ApiEnvelope<unknown>>(`/api/v1/cameras/${cameraId}/ping`, { method: "POST" }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["cameras"] });
      void queryClient.invalidateQueries({ queryKey: ["dashboard-summary"] });
      notify.success(res.message ?? "Ping kamera berhasil.");
    },
    onError: (err) => notify.apiError(err),
  });
}
