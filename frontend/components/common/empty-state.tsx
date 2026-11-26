import { Inbox } from "lucide-react";

type EmptyStateProps = {
  title: string;
  description?: string;
  action?: React.ReactNode;
};

export function EmptyState({ title, description, action }: EmptyStateProps) {
  return (
    <div className="bg-card flex flex-col items-center justify-center gap-2 rounded-xl border p-8 text-center">
      <Inbox className="text-muted-foreground size-6" aria-hidden="true" />
      <p className="text-sm font-semibold">{title}</p>
      {description ? (
        <p className="text-muted-foreground max-w-md text-xs leading-relaxed">{description}</p>
      ) : null}
      {action ? <div className="pt-2">{action}</div> : null}
    </div>
  );
}
