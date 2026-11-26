import { Mono } from "@/components/common/mono";
import { cn } from "@/lib/utils";

type StatCardProps = {
  label: string;
  value: string | number;
  subtitle?: React.ReactNode;
  variant?: "default" | "warning" | "success";
};

export function StatCard({ label, value, subtitle, variant = "default" }: StatCardProps) {
  return (
    <div
      className={cn(
        "bg-card flex flex-col justify-between gap-2 rounded-xl border p-4",
        variant === "warning" && "border-warn-border bg-warn-bg/40",
        variant === "success" && "border-status-online/30",
      )}
    >
      <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
        {label}
      </span>
      <Mono
        className={cn(
          "text-2xl font-bold",
          variant === "warning" && "text-status-conflict",
          variant === "success" && "text-status-online",
        )}
      >
        {value}
      </Mono>
      {subtitle ? <div className="text-muted-foreground text-xs">{subtitle}</div> : null}
    </div>
  );
}
