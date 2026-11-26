import { TriangleAlert } from "lucide-react";
import { Button } from "@/components/ui/button";

type ErrorStateProps = {
  message?: string;
  onRetry?: () => void;
};

export function ErrorState({
  message = "Terjadi kesalahan saat memuat data.",
  onRetry,
}: ErrorStateProps) {
  return (
    <div className="bg-card flex flex-col items-center justify-center gap-3 rounded-xl border p-8 text-center">
      <TriangleAlert className="text-status-conflict size-6" aria-hidden="true" />
      <p className="text-sm font-semibold">Gagal memuat data</p>
      <p className="text-muted-foreground max-w-md text-xs">{message}</p>
      {onRetry ? (
        <Button variant="outline" size="sm" onClick={onRetry}>
          Coba lagi
        </Button>
      ) : null}
    </div>
  );
}
