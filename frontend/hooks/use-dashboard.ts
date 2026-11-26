"use client";

import { useQuery } from "@tanstack/react-query";
import { api, type ApiEnvelope } from "@/lib/api";
import type { DashboardSummary } from "@/types/api";

export function useDashboardSummary() {
  return useQuery({
    queryKey: ["dashboard-summary"],
    queryFn: () =>
      api<ApiEnvelope<DashboardSummary>>("/api/v1/dashboard/summary").then(
        (response) => response.data,
      ),
    refetchInterval: 30_000,
  });
}
