"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, type ApiEnvelope } from "@/lib/api";
import { notify } from "@/lib/toast";
import type { ConflictEvent } from "@/types/api";

export function useConflicts(
  status: "active" | "resolved" | "all" = "active",
  segmentId?: number | null,
) {
  return useQuery({
    queryKey: ["conflicts", status, segmentId ?? "all"],
    queryFn: () => {
      const params = new URLSearchParams();
      if (status !== "all") params.set("status", status);
      if (segmentId) params.set("segment_id", String(segmentId));
      const qs = params.toString();
      return api<ApiEnvelope<ConflictEvent[]>>(`/api/v1/conflict-events${qs ? `?${qs}` : ""}`).then(
        (r) => r.data,
      );
    },
    refetchInterval: 30_000,
  });
}

export function useResolveConflict() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, note }: { id: number; note?: string }) =>
      api<ApiEnvelope<ConflictEvent>>(`/api/v1/conflict-events/${id}/resolve`, {
        method: "POST",
        body: { note },
      }),
    onSuccess: (res) => {
      void queryClient.invalidateQueries({ queryKey: ["conflicts"] });
      void queryClient.invalidateQueries({ queryKey: ["dashboard-summary"] });
      void queryClient.invalidateQueries({ queryKey: ["ip-map"] });
      notify.success(res.message ?? "Konflik ditandai selesai.");
    },
    onError: (err) => notify.apiError(err),
  });
}
