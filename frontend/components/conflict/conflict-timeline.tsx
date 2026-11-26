import { formatDateTime } from "@/lib/format";
import type { ConflictLogItem } from "@/types/api";

export function ConflictTimeline({ logs }: { logs: ConflictLogItem[] }) {
  if (logs.length === 0) {
    return (
      <p className="text-muted-foreground text-xs">
        Belum ada catatan kronologi untuk kejadian ini.
      </p>
    );
  }

  return (
    <ol className="relative ml-2 flex flex-col gap-3 border-l pl-4 text-xs">
      {logs.map((log) => (
        <li key={log.id} className="relative">
          <span
            className="bg-primary absolute top-1.5 -left-[21px] size-2.5 rounded-full"
            aria-hidden="true"
          />
          <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-[11px]">
            <span className="font-mono">{formatDateTime(log.logged_at)}</span>
            <span>·</span>
            <span>{log.actor_name}</span>
          </div>
          <p className="text-foreground mt-0.5 leading-relaxed">{log.message}</p>
        </li>
      ))}
    </ol>
  );
}
