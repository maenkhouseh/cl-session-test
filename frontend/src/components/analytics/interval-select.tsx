"use client";

import { Select } from "@/components/ui/input";
import { INTERVALS } from "@/lib/hooks/use-analytics-params";
import type { Interval } from "@/types/api";

export function IntervalSelect({ value, onChange }: { value: Interval; onChange: (value: Interval) => void }) {
  return (
    <Select aria-label="Date range" className="w-auto min-w-44" value={value} onChange={(e) => onChange(e.target.value as Interval)}>
      {INTERVALS.map((i) => <option key={i.value} value={i.value}>{i.label}</option>)}
    </Select>
  );
}
