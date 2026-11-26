const TIME_ZONE = "Asia/Jakarta";

const dateTimeFormat = new Intl.DateTimeFormat("id-ID", {
  timeZone: TIME_ZONE,
  day: "numeric",
  month: "short",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
});

const shortDateTimeFormat = new Intl.DateTimeFormat("id-ID", {
  timeZone: TIME_ZONE,
  day: "numeric",
  month: "short",
  hour: "2-digit",
  minute: "2-digit",
});

const timeFormat = new Intl.DateTimeFormat("id-ID", {
  timeZone: TIME_ZONE,
  hour: "2-digit",
  minute: "2-digit",
});

const dayKey = new Intl.DateTimeFormat("en-CA", { timeZone: TIME_ZONE });

const numberFormat = new Intl.NumberFormat("id-ID");

const oneDecimalFormat = new Intl.NumberFormat("id-ID", {
  minimumFractionDigits: 0,
  maximumFractionDigits: 1,
});

export const EMPTY = "—";

/** MAC from the API is lowercase (Postgres macaddr); the UI shows it uppercase. */
export function formatMac(mac: string | null | undefined): string {
  return mac ? mac.toUpperCase() : EMPTY;
}

export function formatNumber(value: number): string {
  return numberFormat.format(value);
}

export function formatPercent(value: number): string {
  if (value > 0 && value < 10) {
    return `${oneDecimalFormat.format(value)}%`;
  }

  return `${Math.round(value)}%`;
}

export function formatBytes(bytes: number): string {
  if (bytes <= 0) {
    return "0 GB";
  }

  if (bytes >= 1_000_000_000_000) {
    return `${oneDecimalFormat.format(bytes / 1_000_000_000_000)} TB`;
  }

  if (bytes >= 1_000_000_000) {
    return `${oneDecimalFormat.format(bytes / 1_000_000_000)} GB`;
  }

  return `${oneDecimalFormat.format(bytes / 1_000_000)} MB`;
}

export function formatOctet(octet: number): string {
  return `.${octet}`;
}

export function formatOctetRange(start: number, end: number): string {
  return start === end ? `.${start}` : `.${start}–.${end}`;
}

export function formatDateTime(iso: string | null | undefined): string {
  return iso ? dateTimeFormat.format(new Date(iso)).replace(/\./g, ":") : EMPTY;
}

/** Relative time for recent timestamps (< 24h) or formatted date. */
export function formatRelativeTime(iso: string | null | undefined, now: Date = new Date()): string {
  if (!iso) {
    return EMPTY;
  }

  const date = new Date(iso);
  const diffSeconds = Math.max(0, Math.floor((now.getTime() - date.getTime()) / 1000));

  if (diffSeconds < 60) {
    return `${diffSeconds} detik lalu`;
  }

  const diffMinutes = Math.floor(diffSeconds / 60);
  if (diffMinutes < 60) {
    return `${diffMinutes} menit lalu`;
  }

  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) {
    return `${diffHours} jam lalu`;
  }

  return formatLastSeen(iso, now);
}

/** "Hari ini 08:02", "Kemarin 17:20", otherwise "26 Okt 13:40". */
export function formatLastSeen(iso: string | null | undefined, now: Date = new Date()): string {
  if (!iso) {
    return EMPTY;
  }

  const date = new Date(iso);
  const time = timeFormat.format(date).replace(/\./g, ":");
  const yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);

  if (dayKey.format(date) === dayKey.format(now)) {
    return `Hari ini ${time}`;
  }
  if (dayKey.format(date) === dayKey.format(yesterday)) {
    return `Kemarin ${time}`;
  }

  return shortDateTimeFormat.format(date).replace(/\./g, ":");
}

export function ipToNumber(ip: string): number {
  const parts = ip.split(".").map((part) => Number.parseInt(part, 10));
  if (parts.length !== 4 || parts.some((n) => Number.isNaN(n))) {
    return 0;
  }

  return (
    ((parts[0] ?? 0) << 24) + ((parts[1] ?? 0) << 16) + ((parts[2] ?? 0) << 8) + (parts[3] ?? 0)
  );
}

export function initialOf(nameOrEmail: string): string {
  const letter = nameOrEmail.replace(/[^\p{L}]/gu, "").charAt(0);

  return letter ? letter.toUpperCase() : "?";
}
