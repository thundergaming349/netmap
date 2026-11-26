"use client";

import { VideoOff } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { Mono } from "@/components/common/mono";
import type { Camera } from "@/types/api";

type CameraPlayerProps = {
  camera: Camera;
  quality?: "sub" | "main";
};

/**
 * Plays live stream directly from go2rtc (:1984/api/stream.mjpeg) only while visible in the viewport.
 * Connects directly to go2rtc (CORS origin: "*") to avoid Next.js dev proxy ECONNRESET errors,
 * and supports Boa HTTP MJPEG, IPC UDP RTSP, and Happytime/Hikvision H.265/H.264 cameras on all browsers.
 */
export function CameraPlayer({ camera, quality = "sub" }: CameraPlayerProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const imgRef = useRef<HTMLImageElement>(null);
  const [isVisible, setIsVisible] = useState(false);
  const [isTabVisible, setIsTabVisible] = useState(
    () => typeof document === "undefined" || document.visibilityState === "visible",
  );
  const [clock, setClock] = useState<string>("");

  const streamName = `${camera.stream_key}_${quality}`;
  const rawHost = typeof window !== "undefined" ? window.location.hostname : "127.0.0.1";
  const isLocalHost = rawHost === "localhost" || rawHost.startsWith("127.");
  // Shard across 127.0.0.2..250/8 loopback aliases so browsers never hit the 6-connections-per-origin HTTP/1.1 limit in 3x3 / 4x4 mode
  const shardOctet = (camera.id % 240) + 2;
  const streamHost = isLocalHost ? `127.0.0.${shardOctet}` : rawHost;
  const [streamState, setStreamState] = useState<{
    key: string;
    status: "loading" | "active" | "hud";
    retry: number;
  }>({
    key: streamName,
    status: "loading",
    retry: 0,
  });

  const status = streamState.key === streamName ? streamState.status : "loading";
  const retry = streamState.key === streamName ? streamState.retry : 0;
  const streamActive = status === "active";
  const mjpegSrc = `http://${streamHost}:1984/api/stream.mjpeg?src=${encodeURIComponent(streamName)}&r=${retry}`;

  useEffect(() => {
    const node = containerRef.current;
    if (!node) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries[0];
        if (entry) {
          setIsVisible(entry.isIntersecting);
        }
      },
      { threshold: 0.1 },
    );

    observer.observe(node);
    return () => observer.disconnect();
  }, []);

  // Pause and sever live streams when browser tab is hidden to save CPU & bandwidth
  useEffect(() => {
    const handleVisibility = () => {
      setIsTabVisible(document.visibilityState === "visible");
    };

    document.addEventListener("visibilitychange", handleVisibility);
    return () => document.removeEventListener("visibilitychange", handleVisibility);
  }, []);

  const canAttemptStream = isVisible && isTabVisible && status !== "hud";

  // Ensure img.src is attached while active (surviving React StrictMode double-effect invocation)
  // and explicitly cleared when unmounting or leaving the viewport so the MJPEG TCP socket closes immediately.
  useEffect(() => {
    const imgEl = imgRef.current;
    if (imgEl && canAttemptStream && imgEl.getAttribute("src") !== mjpegSrc) {
      imgEl.src = mjpegSrc;
    }

    return () => {
      if (imgEl) {
        imgEl.removeAttribute("src");
      }
    };
  }, [mjpegSrc, canAttemptStream]);

  // Automatically retry stream connection every 6s if it temporarily fails (e.g. LAN reconnect)
  useEffect(() => {
    if (!isVisible || camera.status === "offline" || status !== "hud") return;

    const timer = window.setTimeout(() => {
      setStreamState((prev) => ({
        key: streamName,
        status: "loading",
        retry: (prev.key === streamName ? prev.retry : 0) + 1,
      }));
    }, 6000);

    return () => window.clearTimeout(timer);
  }, [camera.status, isVisible, status, streamName]);

  useEffect(() => {
    if (!isVisible || camera.status === "offline") return;

    const updateClock = () => {
      setClock(
        new Intl.DateTimeFormat("id-ID", {
          timeZone: "Asia/Jakarta",
          day: "2-digit",
          month: "short",
          year: "numeric",
          hour: "2-digit",
          minute: "2-digit",
          second: "2-digit",
        })
          .format(new Date())
          .replace(/\./g, ":"),
      );
    };

    updateClock();
    const interval = window.setInterval(updateClock, 1000);
    return () => window.clearInterval(interval);
  }, [camera.status, isVisible]);

  if (camera.status === "offline") {
    return (
      <div
        ref={containerRef}
        className="bg-background flex aspect-video w-full flex-col items-center justify-center gap-1.5 p-4 text-center"
      >
        <VideoOff className="text-status-offline-fg size-6" aria-hidden="true" />
        <span className="text-status-offline-fg text-xs font-semibold">SINYAL TERPUTUS</span>
        <span className="text-muted-foreground text-[11px]">
          Tidak ada respons RTSP dari <Mono>{camera.ip}</Mono>
        </span>
      </div>
    );
  }

  return (
    <div
      ref={containerRef}
      className="bg-background relative flex aspect-video w-full flex-col justify-between overflow-hidden p-3 select-none"
    >
      {/* Direct go2rtc live MJPEG stream: rendered at z-10 without opacity-0 because multipart/x-mixed-replace
          infinite streams never close, so img.onLoad does not fire in standard browsers. */}
      {canAttemptStream ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          ref={imgRef}
          key={mjpegSrc}
          src={mjpegSrc}
          alt=""
          onLoad={() =>
            setStreamState((prev) => ({ key: streamName, status: "active", retry: prev.retry }))
          }
          onError={() =>
            setStreamState((prev) => ({ key: streamName, status: "hud", retry: prev.retry }))
          }
          className="pointer-events-none absolute inset-0 z-10 h-full w-full object-cover"
        />
      ) : null}

      {/* Fallback HUD background (z-0, automatically covered by z-10 live video as soon as the first frame arrives) */}
      <div
        className="pointer-events-none absolute inset-0 z-0 opacity-20"
        style={{
          backgroundImage:
            "radial-gradient(circle at 50% 45%, #1d2733 0%, #0e1116 75%), repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(76,194,255,0.06) 4px)",
        }}
        aria-hidden="true"
      />

      <div className="relative z-20 flex items-center justify-between gap-2 text-[11px]">
        <Mono className="bg-background/80 text-muted-foreground rounded px-1.5 py-0.5">
          CH{String(camera.channel).padStart(2, "0")} · {camera.location_group}
        </Mono>
        <Mono className="bg-background/80 text-foreground rounded px-1.5 py-0.5">{clock}</Mono>
      </div>

      {!streamActive ? (
        <div className="relative z-0 my-auto flex flex-col items-center gap-1 px-3 text-center">
          <span className="text-foreground/90 text-xs font-semibold">
            {camera.name} ({camera.brand === "hikvision" ? "HIKVISION" : "ONVIF/IPC"})
          </span>
          <Mono className="text-muted-foreground text-[11px]">
            {camera.ip} · {quality === "main" ? `${camera.resolution} · Main` : "640x360 · Sub"} ·{" "}
            {camera.fps} fps
          </Mono>
          {status === "hud" ? (
            <span className="mt-1 max-w-md rounded border border-amber-500/30 bg-amber-950/60 px-2.5 py-1 text-[10px] text-amber-200">
              {camera.brand === "hikvision"
                ? `RTSP 401 Unauthorized pada ${camera.ip}: Password kamera ini berbeda dari pengaturan global. Periksa password unit di webview http://${camera.ip}.`
                : `Menunggu autentikasi RTSP dari ${camera.ip}. Periksa kredensial di Pengaturan → Stream CCTV.`}
            </span>
          ) : (
            <Mono className="text-primary text-[10px]">Memuat Live Stream dari {camera.ip}...</Mono>
          )}
          {camera.status === "unstable" ? (
            <span className="text-status-risk mt-1 rounded border border-amber-500/30 bg-amber-950/50 px-2 py-0.5 text-[10px]">
              Jitter tinggi — terdeteksi konflik IP pada {camera.ip}
            </span>
          ) : null}
        </div>
      ) : null}

      <div className="relative z-20 flex items-center justify-between text-[10px]">
        <Mono className="bg-background/80 text-muted-foreground rounded px-1.5 py-0.5">
          {camera.switch_name ? `${camera.switch_name} · ${camera.port ?? ""}` : camera.ip}
        </Mono>
        <Mono className="bg-background/80 text-muted-foreground rounded px-1.5 py-0.5">
          {camera.latency_ms ? `${camera.latency_ms} ms` : "2 ms"}
        </Mono>
      </div>
    </div>
  );
}
