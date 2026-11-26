import type { Metadata } from "next";
import { Suspense } from "react";
import { IpMapView } from "@/components/ip-map/ip-map-view";
import { Skeleton } from "@/components/ui/skeleton";

export const metadata: Metadata = { title: "Peta IP per Jalur" };

export default function IpMapPage() {
  return (
    <Suspense fallback={<Skeleton className="h-96 w-full" />}>
      <IpMapView />
    </Suspense>
  );
}
