"use client";

import { X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { countryName } from "@/lib/utils/format";

const LABELS: Record<string, string> = {
  linkId: "Link", folderId: "Folder", tagIds: "Tags", trigger: "Trigger", domain: "Domain", key: "Key", country: "Country", region: "Region",
  city: "City", continent: "Continent", device: "Device", browser: "Browser", os: "OS", referer: "Referrer", refererUrl: "Referrer URL", url: "URL",
  utm_source: "UTM source", utm_medium: "UTM medium", utm_campaign: "UTM campaign", utm_term: "UTM term", utm_content: "UTM content",
};

function describe(key: string, value: string, names?: Record<string, string>): string {
  const negated = value.startsWith("-");
  const raw = negated ? value.slice(1) : value;
  const shown = key === "country" ? countryName(raw) : names?.[raw] ?? raw;
  return `${LABELS[key] ?? key} ${negated ? "is not" : "is"} ${shown}`;
}

interface Props {
  filters: Record<string, string>;
  /** Optional id → display name (links, folders) so pills never show raw ULIDs. */
  names?: Record<string, string>;
  onRemove: (key: string) => void;
  onClear: () => void;
}

export function FilterPills({ filters, names, onRemove, onClear }: Props) {
  const entries = Object.entries(filters);
  if (entries.length === 0) return null;

  return (
    <div className="flex flex-wrap items-center gap-2" aria-label="Active filters">
      {entries.map(([key, value]) => (
        <span key={key} className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card py-1 pl-3 pr-1.5 text-sm">
          {describe(key, value, names)}
          <button type="button" aria-label={`Remove filter ${LABELS[key] ?? key}`} onClick={() => onRemove(key)} className="rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground">
            <X className="size-3.5" />
          </button>
        </span>
      ))}
      {entries.length > 1 && <Button variant="ghost" size="sm" onClick={onClear}>Clear all</Button>}
    </div>
  );
}
