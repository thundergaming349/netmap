# 07 · Roadmap

## Tahap 0 — Persiapan

- [ ] Install OS di mini PC (Ubuntu Server 24.04 / Debian 12), IP static `10.10.99.5`
- [ ] Docker + Docker Compose
- [ ] Colok mini PC ke port trunk, buat sub-interface VLAN (lihat [03-arsitektur.md](03-arsitektur.md#jaringan-mini-pc))
- [ ] Reservasi IP `.250` di tiap VLAN untuk mini PC
- [ ] Setup user `netmap-api` + REST API di MikroTik (lihat [04-integrasi-mikrotik.md](04-integrasi-mikrotik.md))
- [ ] Tes `curl` ke `/rest/ip/arp` berhasil
- [ ] Data jalur/VLAN asli dicatat (ganti contoh di [01-konsep.md](01-konsep.md))
- [x] Stack final: Laravel + PostgreSQL + Next.js (shadcn/ui) — lihat [`../rules/`](../rules/README.md)
- [ ] Domain `netmap.<domain-kantor>` + DNS static di MikroTik + sertifikat HTTPS (DNS-01)
- [x] Project Google Cloud: OAuth client (Web), redirect URI terdaftar

## Tahap 1 — Read-only (inti IPAM)

- [x] Scaffold project: `backend/` (Laravel), `frontend/` (Next.js + shadcn), `infra/` (docker-compose, Caddy, go2rtc)
- [x] Login dengan Google + allowlist email + halaman Pengguna & akses
- [x] Skema database + migrasi
- [x] Collector: address, pool, lease, arp, interface, route, mangle
- [x] Layar **Ringkasan** (data asli)
- [x] Layar **Peta IP** + Cek IP sebelum dipasang
- [x] Layar **Perangkat** (CRUD di NetMap + ekspor/impor CSV)
- [x] Layar **Route per Jalur** (topologi dari data + trace per perangkat + tabel `/ip route`)
- [x] Peran admin / technician / cctv_viewer
- [x] Pengaturan: koneksi MikroTik & sinkronisasi

## Tahap 2 — Deteksi konflik & notifikasi

- [x] `arp_observations` + scanner (`fping`, `arping -D`)
- [x] Rule engine (DUP_IP, RES_MISUSE, REG_MISMATCH, STATIC_IN_POOL, OUT_OF_SUBNET, NEW_DEVICE, CAM_OFFLINE)
- [x] Layar **Log Konflik**
- [x] Webhook lease-script & netwatch
- [x] Notifikasi Telegram
- [x] (Opsional) SNMP switch managed → lokasi port + MAC_FLAP

## Tahap 3 — Live CCTV

- [x] Container go2rtc (`infra/docker-compose.yml`)
- [x] Tabel kamera/NVR + pola RTSP per merek
- [x] Layar **Live CCTV** (grid sub-stream, perbesar main-stream, snapshot)
- [x] Status kamera (ping + go2rtc) & panel NVR
- [x] Peran **Viewer CCTV**

## Tahap 4 — Aksi tulis ke MikroTik

- [ ] Ganti group user API di router fisik MikroTik ke `netmap-rw`
- [x] Buat DHCP lease static, ARP static, blokir MAC, restart PoE
- [x] Konfirmasi di UI + `audit_log`
- [x] Peran **Teknisi**
- [x] Backup harian (DB + `/export` MikroTik)

## Nanti

- Mode terang
- Multi-router (lebih dari satu MikroTik / cabang)
- Playback rekaman langsung di dashboard
- Laporan bulanan (pemakaian IP, uptime kamera, kejadian konflik)
