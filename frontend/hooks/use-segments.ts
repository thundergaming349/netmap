"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, type ApiEnvelope } from "@/lib/api";
import { notify } from "@/lib/toast";
import type { IpCheckResult, IpMapData, IpReservation, Segment } from "@/types/api";

export function useSegments() {
  return useQuery({
    queryKey: ["segments"],
    queryFn: () => api<ApiEnvelope<Segment[]>>("/api/v1/segments").then((r) => r.data),
    refetchInterval: 10_000,
  });
}

export function useIpMap(segmentId: number | null) {
  return useQuery({
    queryKey: ["ip-map", segmentId],
    queryFn: () =>
      api<ApiEnvelope<IpMapData>>(`/api/v1/segments/${segmentId}/ip-map`).then((r) => r.data),
    enabled: segmentId !== null,
    refetchInterval: 10_000,
  });
}

export function useIpCheck(ip: string, segmentId?: number | null) {
  const trimmed = ip.trim();
  const isIpv4Candidate = /^(\d{1,3}\.){3}\d{1,3}$/.test(trimmed);

  return useQuery({
    queryKey: ["ip-check", trimmed, segmentId ?? null],
    queryFn: () => {
      const params = new URLSearchParams({ ip: trimmed });
      if (segmentId !== undefined && segmentId !== null) {
        params.set("segment_id", String(segmentId));
      }
      return api<ApiEnvelope<IpCheckResult>>(`/api/v1/ip-check?${params.toString()}`).then(
        (r) => r.data,
      );
    },
    enabled: isIpv4Candidate,
  });
}

export function useReservations(segmentId?: number | null) {
  return useQuery({
    queryKey: ["ip-reservations", segmentId ?? "all"],
    queryFn: () => {
      const qs = segmentId ? `?segment_id=${segmentId}` : "";
      return api<ApiEnvelope<IpReservation[]>>(`/api/v1/ip-reservations${qs}`).then((r) => r.data);
    },
  });
}

export function useCreateReservation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: {
      segment_id: number;
      start_octet: number;
      end_octet: number;
      label: string;
      description?: string;
    }) =>
      api<ApiEnvelope<IpReservation>>("/api/v1/ip-reservations", { method: "POST", body: input }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["ip-reservations"] });
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      notify.success(res.message ?? "Reservasi IP disimpan.");
    },
    onError: (err) => notify.apiError(err),
  });
}

export function useDeleteReservation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      api<{ message: string }>(`/api/v1/ip-reservations/${id}`, { method: "DELETE" }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["ip-reservations"] });
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      notify.success(res.message);
    },
    onError: (err) => notify.apiError(err),
  });
}
