/** Compact number: 1234 → "1.2K", 1_500_000 → "1.5M". */
export function formatCompact(value: number): string {
  return new Intl.NumberFormat("en", { notation: "compact", maximumFractionDigits: 1 }).format(value);
}

export function formatNumber(value: number): string {
  return new Intl.NumberFormat("en").format(value);
}

/** Cents → "$1,234.50" (whole dollars when there are no cents). */
export function formatCurrency(cents: number, currency = "USD"): string {
  const hasCents = cents % 100 !== 0;
  return new Intl.NumberFormat("en", {
    style: "currency",
    currency: currency.toUpperCase(),
    minimumFractionDigits: hasCents ? 2 : 0,
    maximumFractionDigits: 2,
  }).format(cents / 100);
}

export function formatPercent(part: number, whole: number): string {
  if (whole <= 0) return "0%";
  return `${Math.round((part / whole) * 1000) / 10}%`;
}

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ["year", 31_536_000],
  ["month", 2_592_000],
  ["week", 604_800],
  ["day", 86_400],
  ["hour", 3_600],
  ["minute", 60],
];

/** "3 minutes ago", "in 2 days". */
export function timeAgo(date: string | Date, now: Date = new Date()): string {
  const seconds = (new Date(date).getTime() - now.getTime()) / 1000;
  const rtf = new Intl.RelativeTimeFormat("en", { numeric: "auto" });
  for (const [unit, size] of UNITS) {
    if (Math.abs(seconds) >= size) return rtf.format(Math.round(seconds / size), unit);
  }
  return "just now";
}

export function formatDateTime(date: string | Date): string {
  return new Intl.DateTimeFormat("en", { dateStyle: "medium", timeStyle: "short" }).format(new Date(date));
}

export function formatDate(date: string | Date): string {
  return new Intl.DateTimeFormat("en", { dateStyle: "medium" }).format(new Date(date));
}

/** Country code → flag emoji ("DE" → 🇩🇪). */
export function countryFlag(code?: string | null): string {
  if (!code || code.length !== 2) return "🌐";
  return String.fromCodePoint(...[...code.toUpperCase()].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));
}

const regionNames = typeof Intl !== "undefined" && "DisplayNames" in Intl ? new Intl.DisplayNames(["en"], { type: "region" }) : null;

export function countryName(code?: string | null): string {
  if (!code) return "Unknown";
  try {
    return regionNames?.of(code.toUpperCase()) ?? code;
  } catch {
    return code;
  }
}

const CONTINENTS: Record<string, string> = {
  AF: "Africa", AN: "Antarctica", AS: "Asia", EU: "Europe", NA: "North America", OC: "Oceania", SA: "South America",
};

export function continentName(code?: string | null): string {
  return (code && CONTINENTS[code.toUpperCase()]) || code || "Unknown";
}
