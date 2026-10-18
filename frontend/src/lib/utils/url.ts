/** "https://www.example.com/a?b=1" → "example.com/a". Used for compact destination display. */
export function displayUrl(url: string, max = 60): string {
  try {
    const u = new URL(url);
    const text = `${u.host.replace(/^www\./, "")}${u.pathname === "/" ? "" : u.pathname}`;
    return text.length > max ? `${text.slice(0, max - 1)}…` : text;
  } catch {
    return url;
  }
}

export function hostOf(url: string): string {
  try {
    return new URL(url).host.replace(/^www\./, "");
  } catch {
    return url;
  }
}

export function faviconUrl(url: string, size = 64): string {
  return `https://www.google.com/s2/favicons?sz=${size}&domain_url=${encodeURIComponent(hostOf(url))}`;
}

/** Same-origin QR endpoint exposed by the API. */
export function qrUrl(shortLink: string, opts: { size?: number; fg?: string; bg?: string; format?: "svg" | "png" } = {}): string {
  const params = new URLSearchParams({ url: shortLink });
  if (opts.size) params.set("size", String(opts.size));
  if (opts.fg) params.set("fgColor", opts.fg.replace("#", ""));
  if (opts.bg) params.set("bgColor", opts.bg.replace("#", ""));
  if (opts.format) params.set("format", opts.format);
  return `/api/v1/qr?${params}`;
}

/** Drops empty values so they never reach the query string. */
export function toQuery(params: Record<string, unknown>): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "") continue;
    if (Array.isArray(value)) {
      if (value.length) search.set(key, value.join(","));
    } else {
      search.set(key, String(value));
    }
  }
  const out = search.toString();
  return out ? `?${out}` : "";
}
