import type { Metadata } from "next";
import { PageHeader } from "@/components/common/page-header";
import { SettingsNav } from "@/components/settings/settings-nav";
import { UsersSettings } from "@/components/settings/users-settings";

export const metadata: Metadata = { title: "Pengguna & akses" };

export default function UsersSettingsPage() {
  return (
    <div className="flex max-w-5xl flex-col gap-5">
      <PageHeader
        title="Pengaturan"
        description="Konfigurasi koneksi MikroTik RouterOS, jadwal sinkronisasi, aturan deteksi konflik, notifikasi Telegram, go2rtc CCTV, pengguna, dan backup."
      />
      <SettingsNav />
      <UsersSettings />
    </div>
  );
}
