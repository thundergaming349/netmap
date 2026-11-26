"use client";

import { useQuery } from "@tanstack/react-query";
import { api, type ApiEnvelope } from "@/lib/api";
import type { RouterRoute, TopologyData } from "@/types/api";

export function useTopology() {
  return useQuery({
    queryKey: ["topology"],
    queryFn: () => api<ApiEnvelope<TopologyData>>("/api/v1/topology").then((r) => r.data),
    refetchInterval: 10_000,
  });
}

export function useRouterRoutes() {
  return useQuery({
    queryKey: ["router-routes"],
    queryFn: () => api<ApiEnvelope<RouterRoute[]>>("/api/v1/routes").then((r) => r.data),
    refetchInterval: 15_000,
  });
}
