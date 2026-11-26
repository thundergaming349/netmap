"use client";

import { Download, Plus, Search, Upload } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { PageHeader } from "@/components/common/page-header";
import { SegmentTabs } from "@/components/segment/segment-tabs";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useDevices, useImportDevices } from "@/hooks/use-devices";
import { useCan } from "@/hooks/use-me";
import { useSegments } from "@/hooks/use-segments";
import { DEVICE_TYPE_OPTIONS } from "@/lib/status";
import type { Device } from "@/types/api";
import { DeviceForm } from "./device-form";
import { DeviceTable } from "./device-table";

const TYPE_FILTER_ITEMS = [{ value: "all", label: "Semua Tipe" }, ...DEVICE_TYPE_OPTIONS];

const STATUS_FILTER_ITEMS = [
  { value: "all", label: "Semua Status" },
  { value: "online", label: "Online" },
  { value: "conflict", label: "Konflik" },
  { value: "risk", label: "Rawan" },
  { value: "offline", label: "Offline" },
];

export function DevicesView() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const canManage = useCan("manage-devices");

  const segmentsQuery = useSegments();
  const segments = segmentsQuery.data ?? [];

  const initialSearch = searchParams.get("q") ?? "";
  const initialAdd = searchParams.get("tambah") === "1";

  const [selectedSegmentId, setSelectedSegmentId] = useState<number | null>(null);
  const [typeFilter, setTypeFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState("all");
  const [searchQuery, setSearchQuery] = useState(initialSearch);
  const [showForm, setShowForm] = useState(initialAdd);
  const [editingDevice, setEditingDevice] = useState<Device | null>(null);
  const [showCsvImport, setShowCsvImport] = useState(false);
  const [csvText, setCsvText] = useState("");

  const devicesQuery = useDevices({
    segment_id: selectedSegmentId,
    type: typeFilter,
    status: statusFilter,
    q: searchQuery,
  });

  const importDevices = useImportDevices();

  async function handleCsvImport(e: React.FormEvent) {
    e.preventDefault();
    if (!csvText.trim()) return;
    await importDevices.mutateAsync(csvText);
    setCsvText("");
    setShowCsvImport(false);
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <PageHeader
          title="Inventaris Perangkat"
          description="Daftar seluruh perangkat static dan lease static di semua jalur, lengkap dengan validasi IP bebas bentrok."
        />
        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            render={
              <a
                href={`/api/v1/devices/export${selectedSegmentId ? `?segment_id=${selectedSegmentId}` : ""}`}
                download
              />
            }
          >
            <Download className="size-4" aria-hidden="true" />
            Ekspor CSV
          </Button>
          {canManage ? (
            <Button variant="outline" size="sm" onClick={() => setShowCsvImport((v) => !v)}>
              <Upload className="size-4" aria-hidden="true" />
              Impor CSV
            </Button>
          ) : null}
          {canManage ? (
            <Button
              onClick={() => {
                setEditingDevice(null);
                setShowForm(true);
              }}
            >
              <Plus className="size-4" aria-hidden="true" />
              Tambah Perangkat
            </Button>
          ) : null}
        </div>
      </div>

      {/* Add / Edit Device Form Card */}
      {(showForm || editingDevice !== null) && segments.length > 0 ? (
        <div className="bg-card border-primary/50 flex flex-col gap-4 rounded-xl border p-5">
          <div className="flex items-center justify-between border-b pb-3">
            <h2 className="text-base font-semibold">
              {editingDevice ? `Edit Perangkat: ${editingDevice.name}` : "Tambah Perangkat Baru"}
            </h2>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                setShowForm(false);
                setEditingDevice(null);
                if (initialAdd) router.replace("/perangkat");
              }}
            >
              Tutup
            </Button>
          </div>
          <DeviceForm
            segments={segments}
            initialSegmentId={selectedSegmentId ?? segments[0]?.id}
            editingDevice={editingDevice}
            onSuccess={() => {
              setShowForm(false);
              setEditingDevice(null);
            }}
            onCancel={() => {
              setShowForm(false);
              setEditingDevice(null);
            }}
          />
        </div>
      ) : null}

      {/* CSV Import Box */}
      {showCsvImport ? (
        <form
          onSubmit={(e) => void handleCsvImport(e)}
          className="bg-card flex flex-col gap-3 rounded-xl border p-5"
        >
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-semibold">Impor Perangkat dari CSV</h3>
              <p className="text-muted-foreground text-xs">
                Format kolom: <code>name,type,segment,ip,mac,location,switch,port</code>
              </p>
            </div>
            <Button type="button" variant="ghost" size="sm" onClick={() => setShowCsvImport(false)}>
              Tutup
            </Button>
          </div>
          <textarea
            value={csvText}
            onChange={(e) => setCsvText(e.target.value)}
            rows={4}
            placeholder={
              "name,type,segment,ip,mac,location,switch,port\nCam-Gudang-09,camera,CCTV,192.168.20.52,BC:32:5F:1A:0C:52,Gudang,SW-CCTV-02,port 10"
            }
            className="bg-input/30 border-input w-full rounded-lg border p-3 font-mono text-xs focus:outline-none"
          />
          <div className="flex justify-end">
            <Button type="submit" size="sm" disabled={importDevices.isPending}>
              {importDevices.isPending ? "Mengimpor…" : "Proses Impor CSV"}
            </Button>
          </div>
        </form>
      ) : null}

      {/* Segment Filter Tabs */}
      <SegmentTabs
        segments={segments}
        selectedId={selectedSegmentId}
        includeAll
        onSelectAll={() => setSelectedSegmentId(null)}
        onSelect={(seg) => setSelectedSegmentId(seg.id)}
      />

      {/* Search & Filter Controls */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="relative min-w-[240px] flex-1">
          <Search
            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
            aria-hidden="true"
          />
          <Input
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Cari nama perangkat, IP, MAC, atau lokasi…"
            aria-label="Cari perangkat"
            className="pl-9"
          />
        </div>

        <div className="w-44">
          <Select
            items={TYPE_FILTER_ITEMS}
            value={typeFilter}
            onValueChange={(val) => {
              if (val) setTypeFilter(val);
            }}
          >
            <SelectTrigger aria-label="Filter tipe perangkat" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {TYPE_FILTER_ITEMS.map((item) => (
                <SelectItem key={item.value} value={item.value}>
                  {item.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="w-44">
          <Select
            items={STATUS_FILTER_ITEMS}
            value={statusFilter}
            onValueChange={(val) => {
              if (val) setStatusFilter(val);
            }}
          >
            <SelectTrigger aria-label="Filter status perangkat" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {STATUS_FILTER_ITEMS.map((item) => (
                <SelectItem key={item.value} value={item.value}>
                  {item.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <DeviceTable
        devices={devicesQuery.data}
        isLoading={devicesQuery.isPending}
        onEdit={(dev) => {
          setEditingDevice(dev);
          setShowForm(true);
        }}
      />
    </div>
  );
}
