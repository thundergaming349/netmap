import { cn } from "@/lib/utils";

type SegmentDotProps = {
  color?: string | null;
  className?: string;
};

export function SegmentDot({ color = "#4CC2FF", className }: SegmentDotProps) {
  return (
    <span
      className={cn("inline-block size-2.5 shrink-0 rounded-full", className)}
      style={{ backgroundColor: color ?? "#4CC2FF" }}
      aria-hidden="true"
    />
  );
}
