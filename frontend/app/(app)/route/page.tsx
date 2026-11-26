import type { Metadata } from "next";
import { Suspense } from "react";
import { RouteView } from "@/components/topology/route-view";
import { Skeleton } from "@/components/ui/skeleton";

export const metadata: Metadata = { title: "Route per Jalur" };

export default function RoutePage() {
  return (
    <Suspense fallback={<Skeleton className="h-96 w-full" />}>
      <RouteView />
    </Suspense>
  );
}
