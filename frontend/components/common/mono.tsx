import { cn } from "@/lib/utils";

type MonoProps = {
  children: React.ReactNode;
  className?: string;
};

export function Mono({ children, className }: MonoProps) {
  return <span className={cn("font-mono tracking-tight", className)}>{children}</span>;
}
