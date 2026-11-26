import type { Metadata } from "next";
import { Suspense } from "react";
import { ConflictsView } from "@/components/conflict/conflicts-view";
import { Skeleton } from "@/components/ui/skeleton";

export const metadata: Metadata = { title: "Log Konflik" };

export default function ConflictsPage() {
  return (
    <Suspense fallback={<Skeleton className="h-96 w-full" />}>
      <ConflictsView />
    </Suspense>
  );
}
