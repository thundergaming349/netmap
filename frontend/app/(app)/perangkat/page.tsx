import type { Metadata } from "next";
import { Suspense } from "react";
import { DevicesView } from "@/components/device/devices-view";
import { Skeleton } from "@/components/ui/skeleton";

export const metadata: Metadata = { title: "Perangkat" };

export default function DevicesPage() {
  return (
    <Suspense fallback={<Skeleton className="h-96 w-full" />}>
      <DevicesView />
    </Suspense>
  );
}
