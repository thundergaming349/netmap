import { AppShell } from "@/components/layout/app-shell";

// AppShell gates rendering on client-side session validation (/api/v1/me).
export const instant = false;

export default function AppLayout({ children }: { children: React.ReactNode }) {
  return <AppShell>{children}</AppShell>;
}
