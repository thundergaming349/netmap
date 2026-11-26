import type { Metadata } from "next";
import { Suspense } from "react";
import { CctvView } from "@/components/cctv/cctv-view";
import { Skeleton } from "@/components/ui/skeleton";

export const metadata: Metadata = { title: "Live CCTV" };

export default function CctvPage() {
  return (
    <Suspense fallback={<Skeleton className="h-96 w-full" />}>
      <CctvView />
    </Suspense>
  );
}
