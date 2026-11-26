"use client";

import { CheckCircle2, Search, TriangleAlert } from "lucide-react";
import { useEffect, useState } from "react";
import { Mono } from "@/components/common/mono";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useIpCheck } from "@/hooks/use-segments";
import { formatMac } from "@/lib/format";
import { cn } from "@/lib/utils";

type IpCheckerProps = {
  segmentId: number | null;
  defaultPrefix?: string;
  onUseSuggestedIp?: (ip: string) => void;
};

export function IpChecker({ segmentId, defaultPrefix, onUseSuggestedIp }: IpCheckerProps) {
  const [inputIp, setInputIp] = useState("");
  const [debouncedIp, setDebouncedIp] = useState("");

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedIp(inputIp.trim());
    }, 300);

    return () => window.clearTimeout(timer);
  }, [inputIp]);

  const check = useIpCheck(debouncedIp, segmentId);

  return (
    <div className="bg-card flex flex-col gap-3 rounded-xl border p-4 md:p-5">
      <div>
        <h3 className="text-sm font-semibold">Cek IP sebelum dipasang</h3>
        <p className="text-muted-foreground text-xs">
          Ketik alamat IP untuk memastikan belum dipakai perangkat lain, tidak masuk pool DHCP, dan
          tidak bentrok.
        </p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-[220px] flex-1">
          <Search
            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
            aria-hidden="true"
          />
          <Input
            value={inputIp}
            onChange={(e) => setInputIp(e.target.value)}
            placeholder={defaultPrefix ? `Contoh: ${defaultPrefix}.57` : "Contoh: 192.168.20.57"}
            className="pl-9 font-mono"
            aria-label="Alamat IP yang ingin dicek"
          />
        </div>
        {defaultPrefix && !inputIp ? (
          <Button variant="outline" size="sm" onClick={() => setInputIp(`${defaultPrefix}.50`)}>
            Isi contoh <Mono className="ml-1">{defaultPrefix}.50</Mono>
          </Button>
        ) : null}
      </div>

      {check.data ? (
        <div
          className={cn(
            "flex flex-col gap-2 rounded-lg border p-3.5 text-xs",
            check.data.safe
              ? "border-status-online/40 bg-status-online-bg/40"
              : "border-warn-border bg-warn-bg/50",
          )}
        >
          <div className="flex items-center justify-between gap-2">
            <div className="flex items-center gap-2 font-semibold">
              {check.data.safe ? (
                <CheckCircle2 className="text-status-online size-4 shrink-0" aria-hidden="true" />
              ) : (
                <TriangleAlert
                  className="text-status-conflict size-4 shrink-0"
                  aria-hidden="true"
                />
              )}
              <span>
                <Mono>{check.data.ip}</Mono> — {check.data.title}
              </span>
            </div>
            {onUseSuggestedIp && (check.data.safe ? check.data.ip : check.data.suggestion) ? (
              <Button
                size="sm"
                variant="outline"
                onClick={() =>
                  onUseSuggestedIp(
                    (check.data.safe ? check.data.ip : check.data.suggestion) ?? check.data.ip,
                  )
                }
              >
                Pakai{" "}
                <Mono className="ml-1">
                  {check.data.safe ? check.data.ip : check.data.suggestion}
                </Mono>
              </Button>
            ) : null}
          </div>
          <p className="text-muted-foreground leading-relaxed">{check.data.message}</p>
          {check.data.holder ? (
            <div className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 pt-0.5">
              <span>
                Pemegang: <strong className="text-foreground">{check.data.holder.name}</strong>
              </span>
              {check.data.holder.mac ? (
                <span>
                  MAC: <Mono className="text-foreground">{formatMac(check.data.holder.mac)}</Mono>
                </span>
              ) : null}
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
