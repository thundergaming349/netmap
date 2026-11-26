import type { Metadata } from "next";
import { SettingsGroupView } from "@/components/settings/settings-group-view";

export const metadata: Metadata = { title: "Pengaturan" };

export function generateStaticParams() {
  return [
    { bagian: "mikrotik" },
    { bagian: "sinkronisasi" },
    { bagian: "aturan" },
    { bagian: "notifikasi" },
    { bagian: "cctv" },
    { bagian: "backup" },
  ];
}

export default async function SettingsSectionPage({
  params,
}: {
  params: Promise<{ bagian: string }>;
}) {
  const { bagian } = await params;

  return <SettingsGroupView section={bagian} />;
}
