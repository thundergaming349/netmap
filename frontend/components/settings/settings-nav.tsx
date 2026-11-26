"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";

export const SETTINGS_SECTIONS = [
  { slug: "mikrotik", label: "Koneksi MikroTik", href: "/pengaturan/mikrotik" },
  { slug: "sinkronisasi", label: "Sinkronisasi", href: "/pengaturan/sinkronisasi" },
  { slug: "aturan", label: "Aturan Konflik", href: "/pengaturan/aturan" },
  { slug: "notifikasi", label: "Notifikasi Telegram", href: "/pengaturan/notifikasi" },
  { slug: "cctv", label: "Stream CCTV", href: "/pengaturan/cctv" },
  { slug: "pengguna", label: "Pengguna & Akses", href: "/pengaturan/pengguna" },
  { slug: "backup", label: "Backup & Audit Log", href: "/pengaturan/backup" },
] as const;

export function SettingsNav() {
  const pathname = usePathname();

  return (
    <nav
      aria-label="Bagian pengaturan"
      className="flex flex-wrap items-center gap-1.5 border-b pb-3"
    >
      {SETTINGS_SECTIONS.map((item) => {
        const active = pathname === item.href;

        return (
          <Link
            key={item.slug}
            href={item.href}
            aria-current={active ? "page" : undefined}
            className={cn(
              "flex min-h-10 items-center rounded-lg border px-3.5 py-2 text-xs font-medium transition-colors",
              active
                ? "bg-secondary text-foreground border-primary/60 font-semibold"
                : "bg-card text-muted-foreground hover:text-foreground",
            )}
          >
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}

export function SettingsSection({
  title,
  description,
  children,
  footer,
}: {
  title: string;
  description?: string;
  children: React.ReactNode;
  footer?: React.ReactNode;
}) {
  return (
    <section className="bg-card flex flex-col gap-5 rounded-xl border p-5">
      <div className="flex flex-col gap-1 border-b pb-3.5">
        <h2 className="text-base font-semibold">{title}</h2>
        {description ? <p className="text-muted-foreground text-xs">{description}</p> : null}
      </div>
      <div className="flex flex-col gap-4">{children}</div>
      {footer ? (
        <div className="flex flex-wrap items-center justify-end gap-2.5 border-t pt-4">
          {footer}
        </div>
      ) : null}
    </section>
  );
}

export function SettingToggle({
  label,
  description,
  badge,
  checked,
  onCheckedChange,
}: {
  label: string;
  description?: string;
  badge?: React.ReactNode;
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
}) {
  return (
    <label className="bg-panel flex cursor-pointer items-start justify-between gap-4 rounded-xl border p-3.5">
      <div className="flex flex-col gap-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm font-semibold">{label}</span>
          {badge}
        </div>
        {description ? (
          <span className="text-muted-foreground text-xs leading-relaxed">{description}</span>
        ) : null}
      </div>
      <input
        type="checkbox"
        checked={checked}
        onChange={(e) => onCheckedChange(e.target.checked)}
        className="mt-1 size-4 shrink-0 rounded"
      />
    </label>
  );
}
